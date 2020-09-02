<?php

namespace App\Http\Controllers;

use App\Events\OrderPaid;
use App\Exceptions\InvalidRequestException;
use App\Models\Installment;
use App\Models\InstallmentItem;
use Carbon\Carbon;
use Endroid\QrCode\QrCode;
use Illuminate\Http\Request;

class InstallmentsController extends Controller
{
    public function index(Request $request)
    {
        $installments = Installment::query()
            ->where('user_id', $request->user()->id)
            ->paginate(10);

        return view('installments.index', compact('installments'));
    }

    public function show(Installment $installment)
    {
        $this->authorize('own', $installment);
        $items = $installment->items()->orderBy('sequence')->get();

        return view('installments.show', [
            'installment' => $installment,
            'items' => $items,
            'nextItem' => $items->where('paid_at', null)->first(),
        ]);
    }

    // 支付宝付款
    public function payByAlipay(Installment $installment)
    {
        if($installment->order->closed){
            throw new InvalidRequestException('对应的商品订单已被关闭');
        }

        if($installment->status === Installment::STATUS_FINISHED){
            throw new InvalidRequestException('该分期订单已结清');
        }

        if(!$nextItem = $installment->items()->whereNull('paid_at')->orderBy('sequence')->first()){
            throw new InvalidRequestException('该分期订单已结清');
        }

        return app('alipay')->web([
            'out_trade_no' => $installment->no . '_' . $nextItem->sequence,
            'total_amount' => $nextItem->total,
            'subject' => '支付 Laravel Shop 的分期订单: ' . $installment->no,
            'notify_url' => ngrok_url('installments.alipay.notify'),
            'return_url' => route('installments.alipay.return'),

        ]);
    }

    // 支付宝前端回调
    public function alipayReturn()
    {
        try{
            app('alipay')->verify();
        }catch(\Exception $e){
            return view('pages.error', ['msg' => '数据不正确']);
        }

        return view('pages.success', ['msg' => '付款成功']);
    }

    // 支付宝后端回调
    public function alipayNotify()
    {
        $data = app('alipay')->verify();
        if (!in_array($data->trade_status, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            return app('alipay')->success();
        }
        if($this->paid($data->out_trade_no, 'alipay', $data->trade_no)){
            return app('alipay')->success();
        }

        return 'fail';


        return app('alipay')->success();

    }

    // 微信支付
    public function payByWechat(Installment $installment)
    {
        if ($installment->order->closed) {
            throw new InvalidRequestException('对应的商品订单已被关闭');
        }
        if ($installment->status === Installment::STATUS_FINISHED) {
            throw new InvalidRequestException('该分期订单已结清');
        }
        if (!$nextItem = $installment->items()->whereNull('paid_at')->orderBy('sequence')->first()) {
            throw new InvalidRequestException('该分期订单已结清');
        }

        $wechatOrder = app('wechat_pay')->scan([
            'out_trade_no' => $installment->no.'_'.$nextItem->sequence,
            'total_fee'    => $nextItem->total * 100,
            'body'         => '支付 Laravel Shop 的分期订单：'.$installment->no,
            'notify_url'   => '', ngrok_url('installments.wechat.notify'),
        ]);

        $qrCode = new QrCode($wechatOrder->code_url);

        return response($qrCode->writeString(), 200, ['Content-Type' => $qrCode->getContentType()]);
    }

    // 微信回调
    public function wechatNotify()
    {
        $data = app('wechat_pay')->verify();
        if($this->paid($data->out_trade_no, 'wechat', $data->transaction_id)){
            return app('wechat_pay')->success();
        }

        return 'fail';
    }

    protected function paid($outTradeNo, $paymentMethod, $paymentNo)
    {
        list($no, $sequence) = explode('_', $outTradeNo);

        if(!$installment = Installment::where('no', $no)->first()){
            return false;
        }

        if(!$item = $installment->items()->where('sequence', $sequence)->first()){
            return false;
        }
        if($item->paid_at){
            return true;
        }

        \DB::transaction(function() use ($paymentNo, $no, $installment, $item){
            $item->update([
                'paid_at' => Carbon::now(),
                'payment_method' => 'alipay',
                'payment_no' => $paymentNo, // 支付宝订单号
            ]);

            // 如果是第一笔还款
            if($item->sequence === 0){
                $installment->update(['status' => Installment::STATUS_REPAYING]);
                $installment->order->update([
                    'paid_at' => Carbon::now(),
                    'payment_method' => 'installment',
                    'payment_no' => $no,
                ]);
                event(new OrderPaid($installment->order));
            }

            // 如果是最后一笔还款
            if($item->sequence === $installment->count - 1){
                $installment->update(['status' => Installment::STATUS_FINISHED]);
            }
        });

        return true;
    }
}
