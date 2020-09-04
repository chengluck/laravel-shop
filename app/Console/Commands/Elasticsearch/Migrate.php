<?php

namespace App\Console\Commands\Elasticsearch;

use Illuminate\Console\Command;

class Migrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elasticsearch 索引结构迁移';

    protected $es;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->es = app('es');
        // 索引类数组
        $indices = [Indices\ProjectIndex::class];

        foreach($indices as $indexClass){
            // 调用类数组的 getAliasName() 方法来获取索引别名
            $aliasName = $indexClass::getAliasName();
            $this->info('正在处理索引 ' . $aliasName);
            // 通过 exists 方法判断这个别名是否存在
            if(!$this->es->indices()->exists(['index' => $aliasName])){
                $this->info('索引不存在, 准备创建');
                $this->createIndex($aliasName, $indexClass);
                $this->info('创建成功, 准备初始化数据');
                $indexClass::rebuild($aliasName);
                $this->info('操作成功');
                continue;
            }

            // 如果索引已经存在,那么尝试更新索引,如果更新失败会抛出异常
            try{
                $this->info('索引存在, 准备更新');
                $this->updateIndex($aliasName, $indexClass);
            }catch(\Exception $e){
                $this->warn('更新失败, 准备重建');
                $this->reCreateIndex($aliasName, $indexClass);
            }
            $this->info($aliasName . '操作成功');
        }
    }

    // 创建索引
    protected function createIndex($aliasName, $indexClass)
    {
        $this->es->indices()->create([
            'index' => $aliasName . '_0',
            'body' => [
                'settings' => $indexClass::getSettings(),
                'mappings' => [
                    'properties' => $indexClass::getProperties(),
                ],
                'aliases' => [
                    $aliasName => new \stdClass(),
                ]
            ],

        ]);
    }

    // 更新索引
    public function updateIndex($aliasName, $indexClass)
    {
        // 暂时关闭索引
        $this->es->indices()->close(['index' => $aliasName]);
        // 更新索引设置
        $this->es->indices()->putSettings([
            'index' => $aliasName,
            'body' => $indexClass::getSettings(),
        ]);

        // 更新索引字段
        $this->es->indices()->putMapping([
            'index' => $aliasName,
            'body' => [
                'properties' => $indexClass::getProperties(),
            ],
        ]);
        // 重新打开索引
        $this->es->indices()->open(['index' => $aliasName]);
    }

    // 重建索引
    public function reCreateIndex($aliasName, $indexClass)
    {
        // 获取索引信息
        $indexInfo = $this->es->indices()->getAliases(['index' => $aliasName]);
        // 第一个 key 即为索引名称
        $indexName = array_keys($indexInfo)[0];
        if(!preg_match('~_(\d+)$~', $indexName, $m)){
            $msg = '索引名称不正确:' . $indexName;
            $this->error($msg);
            throw new \Exception($msg);
        }
        $newIndexName = $aliasName . '_' . ($m[1] + 1);
        $this->info('正在创建索引' . $newIndexName);
        $this->es->indices()->create([
            'index' => $newIndexName,
            'body' => [
                'settings' => $indexClass::getSettings(),
                'mappings' => [
                    'properties' => $indexClass::getProperties(),
                ],
            ],
        ]);
        $this->info('创建成功, 准备重建数据');
        $indexClass::rebuild($newIndexName);
        $this->info('重建成功, 准备修改别名');
        $this->es->indices()->putAlias(['index' => $newIndexName, 'name' => $aliasName]);
        $this->info('修改成功, 准备删除旧索引');
        $this->es->indices()->delete(['index' => $indexName]);
        $this->info('删除成功');

    }
}
