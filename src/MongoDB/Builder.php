<?php

namespace FilDonadoni\MongoTelescope\MongoDB;

use MongoDB\Laravel\Eloquent\Builder as BaseBuilder;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;
use Illuminate\Support\Facades\Log;

class Builder extends BaseBuilder
{
    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $query = $this->toMongo();
        $start = microtime(true);

        $result = parent::get($columns);

        $time = round((microtime(true) - $start) * 1000, 2);

        $collection = $this->model->getTable();

        $queryString = "db.{$collection}.find(" . json_encode($query) . ")";

        if (class_exists(Telescope::class) && Telescope::isRecording()) {
            $caller = $this->getQueryCaller();

            Telescope::recordQuery(IncomingEntry::make([
                'connection' => 'mongodb',
                'bindings' => [],
                'sql' => $queryString,
                'time' => $time,
                'slow' => $time > 100,
                'file' => $caller['file'] ?? '',
                'line' => $caller['line'] ?? 0,
            ]));
        }

        return $result;
    }

    /**
     * Get the caller information for the query
     *
     * @return array
     */
    protected function getQueryCaller(): array
    {
        $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));

        return $trace->first(function ($frame) {
            return isset($frame['file']) &&
                !str_contains($frame['file'], '/vendor/') &&
                !str_contains($frame['file'], 'Builder.php') &&
                !str_contains($frame['file'], '/MongoDB/');
        }) ?? ['file' => '', 'line' => 0];
    }

    /**
     * Convert the query to MongoDB syntax
     */
    protected function toMongo()
    {
        $query = [];

        Log::debug('Query wheres:', ['wheres' => $this->query->wheres ?? []]);

        if (!empty($this->query->wheres)) {
            foreach ($this->query->wheres as $where) {
                if (!is_array($where)) {
                    continue;
                }

                if (!isset($where['type'])) {
                    continue;
                }

                switch ($where['type']) {
                    case 'Basic':
                        if (isset($where['column'], $where['value'])) {
                            $query[$where['column']] = $where['value'];
                        }
                        break;

                    case 'In':
                        if (isset($where['column'], $where['values'])) {
                            $query[$where['column']] = ['$in' => $where['values']];
                        }
                        break;

                    case 'NotIn':
                        if (isset($where['column'], $where['values'])) {
                            $query[$where['column']] = ['$nin' => $where['values']];
                        }
                        break;

                    case 'Null':
                        if (isset($where['column'])) {
                            $query[$where['column']] = null;
                        }
                        break;

                    case 'NotNull':
                        if (isset($where['column'])) {
                            $query[$where['column']] = ['$ne' => null];
                        }
                        break;
                }
            }
        }

        if (!empty($this->query->orders)) {
            $sort = [];
            foreach ($this->query->orders as $order) {
                if (is_array($order) && isset($order['column'], $order['direction'])) {
                    $sort[$order['column']] = $order['direction'] === 'asc' ? 1 : -1;
                }
            }
            if (!empty($sort)) {
                $query['sort'] = $sort;
            }
        }

        return $query;
    }
}
