<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use PhpParser\Node\Expr\FuncCall;

class Product extends Model
{
    const TYPE_NORMAL = 'normal';
    const TYPE_CROWDFUNDING = 'crowdfunding';
    public static $typeMap = [
        self::TYPE_NORMAL  => '普通商品',
        self::TYPE_CROWDFUNDING => '众筹商品',
    ];

    protected $fillable = [
        'title', 'long_title', 'description', 'image', 'on_sale',
        'rating', 'sold_count', 'review_count', 'price' ,'type'
    ];

    protected $casts = [
        'on_sale' => 'boolean', // on_sale 是一个布尔类型的字段
    ];

    // 与商品SKU关联
    public function skus()
    {
        return $this->hasMany(ProductSku::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function crowdfunding()
    {
        return $this->hasOne(CrowdfundingProduct::class);
    }

    public function properties()
    {
        return $this->hasMany(ProductProperty::class);
    }

    public function getImageUrlAttribute()
    {
        if(Str::startsWith($this->attributes['image'], ['http://', 'https://'])){
            return $this->attributes['image'];
        }

        return \Storage::disk('public')->url($this->attributes['image']);
    }

    public function getGroupedPropertiesAttribute()
    {
        return $this->properties
            ->groupBy('name')
            ->map(function($properties){
                return $properties->pluck('value')->all();
            });
    }

    public function toESArray()
    {
        // 只取出需要的字段
        $arr = Arr::only($this->toArray(),[
            'id',
            'type',
            'title',
            'category_id',
            'long_title',
            'on_sale',
            'rating',
            'sold_count',
            'review_count',
            'price',
        ]);

        // 如果商品有类目, 则 category 字段为类目名数组,否则为空字符串
        $arr['category'] = $this->category ? explode(' - ', $this->category->full_name) : '';
        // 类目的 path 字段
        $arr['category_path'] = $this->category ? $this->category->path : '';
        // strip_tags 函数可以将 html 标签去除
        $arr['description'] = strip_tags($this->description);
        $arr['skus'] = $this->skus->map(function(ProductSku $sku){
            return Arr::only($sku->toArray(), ['title', 'description', 'price']);
        });
        $arr['properties'] = $this->properties->map(function(ProductProperty $property){
            return Arr::only($property->toArray(), ['name', 'value']);
        });

        return $arr;
    }
}
