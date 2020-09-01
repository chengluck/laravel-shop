<?php

namespace App\Services;

use App\Exceptions\CouponcodeUnavailableException;
use App\Exceptions\InternalException;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\ProductSku;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Models\CouponCode;
use Carbon\Carbon;

class OrderService
{
    // 普通下单
    public function store(User $user, UserAddress $address, $remark, $items, CouponCode $coupon = null)
    {
        if($coupon){
            $coupon->checkAvailable($user);
        }
        // 开启一个数据库事务
        $order = \DB::transaction(function () use ($user, $address, $remark, $items, $coupon) {
            // 更新此地址的最后使用时间
            $address->update(['last_used_at' => Carbon::now()]);
            // 创建一个订单
            $order   = new Order([
                'address'      => [ // 将地址信息放入订单中
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => $remark,
                'total_amount' => 0,
                'type' => Order::TYPE_NORMAL,
            ]);
            // 订单关联到当前用户
            $order->user()->associate($user);
            // 写入数据库
            $order->save();

            $totalAmount = 0;
            // 遍历用户提交的 SKU
            foreach ($items as $data) {
                $sku  = ProductSku::find($data['sku_id']);
                // 创建一个 OrderItem 并直接与当前订单关联
                $item = $order->items()->make([
                    'amount' => $data['amount'],
                    'price'  => $sku->price,
                ]);
                $item->product()->associate($sku->product_id);
                $item->productSku()->associate($sku);
                $item->save();
                $totalAmount += $sku->price * $data['amount'];
                if ($sku->decreaseStock($data['amount']) <= 0) {
                    throw new InvalidRequestException('该商品库存不足');
                }
            }

            if($coupon){
                $coupon->checkAvailable($user, $totalAmount);
                $totalAmount = $coupon->getAdjustedPrice($totalAmount);
                $order->couponCode()->associate($coupon);
                if($coupon->changeUsed() <= 0){
                    throw new CouponcodeUnavailableException('该优惠券已被兑完');
                }
            }
            // 更新订单总金额
            $order->update(['total_amount' => $totalAmount]);

            // 将下单的商品从购物车中移除
            $skuIds = collect($items)->pluck('sku_id')->all();
            app(CartService::class)->remove($skuIds);

            return $order;
        });

        // 这里我们直接使用 dispatch 函数
        dispatch(new CloseOrder($order, config('app.order_ttl')));

        return $order;
    }

    // 众筹下单
    public function crowdfunding(User $user, UserAddress $address, ProductSku $sku, $amount)
    {
        // 开启事务
        $order = \DB::transaction(function() use ($amount, $sku, $user, $address){
            $address->update(['last_used_at' => Carbon::now()]);

            $order = new Order([
                'address'      => [ // 将地址信息放入订单中
                    'address'       => $address->full_address,
                    'zip'           => $address->zip,
                    'contact_name'  => $address->contact_name,
                    'contact_phone' => $address->contact_phone,
                ],
                'remark'       => '',
                'total_amount' => $sku->price * $amount,
                'type' => Order::TYPE_CROWDFUNDING,
            ]);

            $order->user()->associate($user);
            $order->save();
            $item = $order->items()->make([
                'amount' => $amount,
                'price' => $sku->price,
            ]);
            $item->product()->associate($sku->product_id);
            $item->productSku()->associate($sku);
            $item->save();

            if($sku->decreaseStock($amount) <= 0){
                throw new InvalidRequestException('该商品库存不足');
            }

            return $order;
        });

        // 众筹结束时间 减去当前时间得到剩余秒数
        $crowdfundingTtl = $sku->product->crowdfunding->end_at->getTimestamp() - time();

        dispatch(new CloseOrder($order, min(config('app.order_ttl'), $crowdfundingTtl)));

        return $order;
    }

    // 同意退款后的逻辑
    public function refundOrder(Order $order)
    {
        switch($order->payment_method){
            case 'wechat':
                $refundNo = Order::getAvailableRefundNo();
                app('wechat_pay')->refund([
                    'out_trade_no' => $order->no,
                    'total_fee' =>$order->total_amount * 100, // 原订单金额, 单位分
                    'refund_fee' => $order->total_amount * 100, // 要退款的订单金额
                    'out_refund_no' => $refundNo, // 退款订单号
                    // 微信支付的退款结果并不是实时返回的,而是通过退款回调来通知,
                    'notify_url' => ngrok_url('payment.wechat.refund_notify') //
                ]);
                $order->update([
                    'refund_no' => $refundNo,
                    'refund_status' => Order::REFUND_STATUS_PROCESSING,
                ]);
                break;
            case 'alipay':
                $refundNo = Order::getAvailableRefundNo();
                $ret = app('alipay')->refund([
                    'out_trade_no' => $order->no,// 订单流水号
                    'refund_amount' => $order->total_amount, // 退款金额, 单位: 元
                    'out_request' => $refundNo, // 退款订单号
                ]);
                // 根据支付宝的文档, 如果返回值里有 sub_code 字段说明是退款失败
                if($ret->sub_code){
                    // 将退款失败的保存,存入 extra 字段
                    $extra = $order->extra;
                    $extra['refund_failed_code'] = $ret->sub_code;
                    // 将订单的退款状态标记为退款失败
                    $order->update([
                        'refund_no' =>$refundNo,
                        'refund_status' => Order::REFUND_STATUS_FAILED,
                        'extra' => $extra,
                    ]);
                }
                break;
            defalt:
                // 原则上不可能出现, 这个只是为了代码健壮性
                throw new InternalException('未知订单支付方式: ' . $order->payment_method);
                break;
        }
    }
}
