<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\SearchBuilders\ProductSearchBuilder;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class ProductsController extends Controller
{

    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 16;
        // 新建查询构造器对象, 设置只搜索上架商品,设置分页
        $builder = (new ProductSearchBuilder())->onsale()->paginate($perPage, $page);


        if($request->input('category_id') && $category = Category::find($request->input('category_id'))){
            // 调用查询构造器的类目筛选
           $builder->category($category);
        }

        if($search = $request->input('search', '')){
            $keywords = array_filter(explode(' ', $search));
            // 调用查询构造器的关键词筛选
            $builder->keywords($keywords);
        }


        if($search || isset($category)){
            // 调用查询构造器的分面搜索
            $builder->aggregateProperties();
        }

        $propertyFilters = [];
        if($filterString = $request->input('filters')){
            $filterArray = explode('|', $filterString);
            foreach($filterArray as $filter){
                list($name, $value) =explode(':', $filter);
                $propertyFilters[$name] = $value;
                // 调用查询构造器的属性筛选
                $builder->propertyFilter($name, $value);
            }
        }

        if($order = $request->input('order', '')){
            if(preg_match('/^(.+)_(asc|desc)$/', $order, $m)){
                if(in_array($m[1], ['price', 'sold_count', 'rating'])){
                    // 调用查询构造器的排序
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        // 最后通过 getParams() 方法取回构造好的查询参数
        $result = app('es')->search($builder->getParams());

        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();

        $properties = [];
        if(isset($result['aggregations'])){
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])
                ->map(function($bucket){
                    return [
                        'key' => $bucket['key'],
                        'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                    ];
                })
                ->filter(function($property) use ($propertyFilters){
                    return count($property['values']) > 1 && !isset($propertyFilters[$property['key']]);
                });
        }

        $products = Product::query()->byIds($productIds)->get();

        $pager = new LengthAwarePaginator($products, $result['hits']['total']['value'], $perPage, $page, [
            'path' => route('products.index', false),
        ]);

        return view('products.index', [
            'products' => $pager,
            'filters' => [
                'search' => $search,
                'order' => $order,
            ],
            'category' => $category ?? null,
            'properties' => $properties,
            'propertyFilters' => $propertyFilters,
        ]);
    }


    /*
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = 16;

        $params = [
            'index' => 'products',
            'body' => [
                'from' => ($page - 1) * $perPage,
                'size' => $perPage,
                'query' => [
                    'bool' => [
                        'filter' => [
                            ['term' => ['on_sale' => true]],
                        ],
                    ],
                ]
            ]
        ];

        $propertyFilters = [];
        if($filterString = $request->input('filters')){
            $filterArray = explode('|', $filterString);
            foreach($filterArray as $filter){
                list($name, $value) =explode(':', $filter);
                $propertyFilters[$name] = $value;
                $params['body']['query']['bool']['filter'][] = [
                    'nested' => [
                        'path' => 'properties',
                        'query' => [
                            ['term' => ['properties.search_value' => $filter]],
                        ],
                    ],
                ];
            }
        }

        if($search = $request->input('search', '')){
            $keywords = array_filter(explode(' ', $search));
            $params['body']['query']['bool']['must'] = [];
            foreach($keywords as $keyword){
                $params['body']['query']['bool']['must'][] = [
                    'multi_match' => [
                        'query' => $keyword,
                        'fields' => [
                            'title^3',
                            'long_title^2',
                            'category^2',
                            'description',
                            'skus_title',
                            'skus_description',
                            'properties_value',
                        ],
                    ],
                ];
            }
        }

        if($request->input('category_id') && $category = Category::find($request->input('category_id'))){
            if($category->is_directory){
                $params['body']['query']['bool']['filter'][] = [
                    // prefix与 like '%{path}%'等价
                    'prefix' => ['category_path' => $category->path . $category->id . '-'],
                ];
            }else{
                $params['body']['query']['bool']['filter'][] = ['term' => ['category_id' => $category->id]];
            }
        }

        if($search || isset($category)){
            $params['body']['aggs'] = [
                'properties' => [
                    'nested' => [
                        'path' => 'properties',
                    ],
                    'aggs' => [
                        'properties' => [
                            'terms' => [
                                'field' => 'properties.name',
                            ],
                            'aggs' => [
                                'value' => [
                                    'terms' => [
                                        'field' => 'properties.value',
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        if($order = $request->input('order', '')){
            if(preg_match('/^(.+)_(asc|desc)$/', $order, $m)){
                if(in_array($m[1], ['price', 'sold_count', 'rating'])){
                    $params['body']['sort'] = [[$m[1] => $m[2]]];
                }
            }
        }

        $result = app('es')->search($params);

        $productIds = collect($result['hits']['hits'])->pluck('_id')->all();

        $properties = [];
        if(isset($result['aggregations'])){
            $properties = collect($result['aggregations']['properties']['properties']['buckets'])
                ->map(function($bucket){
                    return [
                        'key' => $bucket['key'],
                        'values' => collect($bucket['value']['buckets'])->pluck('key')->all(),
                    ];
                })
                ->filter(function($property) use ($propertyFilters){
                    return count($property['values']) > 1 && !isset($propertyFilters[$property['key']]);
                });
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->orderByRaw(sprintf("FIND_IN_SET(id, '%s')", join(',', $productIds)))
            ->get();

        $pager = new LengthAwarePaginator($products, $result['hits']['total']['value'], $perPage, $page, [
            'path' => route('products.index', false),
        ]);

        return view('products.index', [
            'products' => $pager,
            'filters' => [
                'search' => $search,
                'order' => $order,
            ],
            'category' => $category ?? null,
            'properties' => $properties,
            'propertyFilters' => $propertyFilters,
        ]);
    }
    */

    /*
    public function index(Request $request)
    {
        $builder = Product::query()->where('on_sale', true);
        if($search = $request->input('search', '')){
            $like = '%' . $search . '%';
            $builder->where(function($query) use ($like){
                $query->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('skus', function($query) use ($like){
                        $query->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    });
            });
        }

        if($request->input('category_id') && $category = Category::find($request->input('category_id'))){
            if($category->is_directory){
                // 筛选出该父类目下所有子类目的商品
                $builder->whereHas('category', function($query) use ($category){
                    $query->where('path', 'like', $category->path . $category->id . '-%');
                });
            }else{
                $builder->where('category_id', $category->id);
            }
        }

        if($order = $request->input('order', '')){
            if(preg_match('/^(.+)_(asc|desc)$/', $order, $m)){
                if(in_array($m[1], ['price', 'sold_count', 'rating'])){
                    $builder->orderBy($m[1], $m[2]);
                }
            }
        }

        $products = $builder->paginate(16);

        return view('products.index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'order' => $order,
            ],
            'category' => $category ?? null,
            ]);
    }
    */

    public function show(Product $product, Request $request, ProductService $service)
    {
        if(!$product->on_sale){
            throw new InvalidRequestException('商品未上架');
        }

        $favored = false;
        if($user = $request->user()){
            $favored = boolval($user->favoriteProducts()->find($product->id));
        }

        // 商品评价
        $reviews = OrderItem::query()
            ->with(['order.user', 'productSku'])
            ->where('product_id', $product->id)
            ->whereNotNull('reviewed_at')
            ->orderBy('reviewed_at', 'desc')
            ->limit(10)
            ->get();

        // 在Elasticsearch中查询相似的商品
        $similarProductIds  = $service->getSimilarProductIds($product, 4);

        // 根据 Elasticsearch 搜索出来的商品 ID 从数据库中读取商品数据
        $similarProducts = Product::query()->byIds($similarProductIds)->get();

        return view('products.show', [
            'product' => $product,
            'favored' => $favored,
            'reviews' => $reviews,
            'similar' => $similarProducts,
            ]);
    }

    public function favor(Product $product, Request $request)
    {
        $user = $request->user();
        if($user->favoriteProducts()->find($product->id)){
            return [];
        }

        $user->favoriteProducts()->attach($product);
    }

    public function disfavor(Product $product, Request $request)
    {
        $user = $request->user();
        $user->favoriteProducts()->detach($product);

        return [];
    }

    public function favorites(Request $request)
    {
        $products = $request->user()->favoriteProducts()->paginate(16);

        return view('products.favorites', compact('products'));
    }
}

/*
 $params = [
    'index' => 'products',
    'body'  => [
        'query' => [
            'bool' => [
                'filter' => [
                    ['term' => ['on_sale' => true]],
                ],
                'must' => [
                    [
                        'multi_match' => [
                            'query'  => '内存条',
                            'type' => 'best_fields',
                            'fields' => [
                                'title^3',
                                'long_title^2',
                                'category^2',
                                'description',
                                'skus_title',
                                'skus_description',
                                'properties_value',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'aggs' => [
            'properties' => [
                'nested' => [
                    'path' => 'properties',
                ],
                'aggs' => [
                    'properties' => [
                        'terms' => [
                            'field' => 'properties.name',
                        ],
                        'aggs' => [
                            'value' => [
                                'terms' => [
                                    'field' => 'properties.value',
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
];
app('es')->search($params);
*/
