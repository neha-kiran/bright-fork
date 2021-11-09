<?php

declare(strict_types=1);

namespace Diviky\Bright\Database\Concerns;

use BasicQueryFilter\Parser;
use Diviky\Bright\Database\Filters\FilterRelation;
use Diviky\Bright\Database\Filters\FiltersScope;
use Illuminate\Support\Str;

trait Filter
{
    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var array
     */
    protected $aliases = [];

    public function filters(array $filters = [], array $aliases = []): void
    {
        $this->filters = $filters;
        $this->aliases = $aliases;
    }

    /**
     * Add Filters to database query builder.
     *
     * @param array $data
     *
     * @return $this
     */
    public function filter($data = []): self
    {
        $filter = isset($data['filter']) ? $data['filter'] : null;
        if (\is_array($filter)) {
            $this->filterExact($filter);
        }

        $filter = isset($data['parse']) ? $data['parse'] : null;
        if (\is_array($filter)) {
            $this->filterParse($filter);
        }

        $filter = isset($data['dfilter']) ? $data['dfilter'] : null;
        if (\is_array($filter)) {
            $this->filterMatch($filter, $data);
        }

        $filter = isset($data['lfilter']) ? $data['lfilter'] : null;
        if (\is_array($filter)) {
            $this->filterLike($filter);
        }

        $filter = isset($data['rfilter']) ? $data['rfilter'] : null;
        if (\is_array($filter)) {
        }

        $filter = isset($data['efilter']) ? $data['efilter'] : null;
        if (\is_array($filter)) {
            $this->filterLeft($filter);
        }

        $date_range = isset($data['date']) ? $data['date'] : null;
        if (\is_array($date_range)) {
            $this->filterDateRanges($date_range);
        }

        $datetime = isset($data['datetime']) ? $data['datetime'] : null;
        $datetime = $datetime ?: (isset($data['timestamp']) ? $data['timestamp'] : null);

        if (\is_array($datetime)) {
            $this->filterDatetimes($datetime);
        }

        $unixtime = isset($data['unix']) ? $data['unix'] : null;
        $unixtime = $unixtime ?: (isset($data['unixtime']) ? $data['unixtime'] : null);

        if (\is_array($unixtime)) {
            $this->filterUnixTimes($unixtime);
        }

        $ranges = isset($data['range']) ? $data['range'] : null;
        if (\is_array($ranges)) {
            $this->filterRange($ranges);
        }

        $between = isset($data['between']) ? $data['between'] : null;
        if (\is_array($between)) {
            $this->filterBetween($between);
        }

        $scopes = isset($data['scope']) ? $data['scope'] : null;
        if (\is_array($scopes)) {
            $this->filterScopes($scopes);
        }

        return $this;
    }

    protected function filterExact(array $filters = []): self
    {
        foreach ($filters as $column => $value) {
            if (isset($value) && '' != $value[0]) {
                $type = $this->filters[$column] ?? null;

                if (is_null($type)) {
                    $this->addWhere($column, $value);

                    continue;
                }

                if ('scope' == $type) {
                    $this->filterScopes([$column => $value]);
                } elseif ('like' == $type) {
                    $this->filterLike([$column => $value]);
                } elseif ('left' == $type) {
                    $this->filterLeft([$column => $value]);
                } elseif ('right' == $type) {
                    $this->filterRight([$column => $value]);
                } elseif ('between' == $type) {
                    $this->filterBetween([$column => $value]);
                } elseif ('range' == $type) {
                    $this->filterRange([$column => $value]);
                } elseif ('unixtime' == $type) {
                    $this->filterUnixTimes([$column => $value]);
                } elseif ('unix' == $type) {
                    $this->filterUnixTimes([$column => $value]);
                } elseif ('datetime' == $type) {
                    $this->filterDatetimes([$column => $value]);
                } elseif ('timestamp' == $type) {
                    $this->filterDatetimes([$column => $value]);
                } elseif ('date' == $type) {
                    $this->filterDateRanges([$column => $value]);
                } elseif ('parser' == $type) {
                    $this->filterParse([$column => $value]);
                } else {
                    $this->addWhere($column, $value);
                }
            }
        }

        return $this;
    }

    protected function filterLike(array $filters = []): self
    {
        foreach ($filters as $column => $value) {
            if ('' != $value) {
                $value = '%' . $value . '%';

                $this->addWhere($column, $value, 'like');
            }
        }

        return $this;
    }

    protected function filterLeft(array $filters = []): self
    {
        foreach ($filters as $column => $value) {
            if ('' != $value) {
                $value = '%' . $value;
                $this->addWhere($column, $value, 'like');
            }
        }

        return $this;
    }

    protected function filterRight(array $filters = []): self
    {
        foreach ($filters as $column => $value) {
            if ('' != $value) {
                $value = $value . '%';
                $this->addWhere($column, $value, 'like');
            }
        }

        return $this;
    }

    protected function filterMatch(array $filters = [], array $data = []): self
    {
        foreach ($filters as $value => $column) {
            $value = $data[$value];
            if ('' != $value) {
                if (Str::startsWith('%', $column)) {
                    $value = '%' . $value;

                    $this->addWhere(ltrim($column, '%'), $value, 'like');
                } elseif (Str::endsWith('%', $column)) {
                    $value = $value . '%';

                    $this->addWhere(rtrim($column, '%'), $value, 'like');
                } else {
                    $value = '%' . $value . '%';

                    $this->addWhere($column, $value, 'like');
                }
            }
        }

        return $this;
    }

    protected function filterScopes(array $scopes): self
    {
        foreach ($scopes as $scope => $values) {
            if (empty($scope)) {
                continue;
            }

            (new FiltersScope())($this->getEloquent(), $values, $scope);
        }

        return $this;
    }

    protected function filterDateRanges(array $date_range): self
    {
        foreach ($date_range as $column => $date) {
            if (empty($date)) {
                continue;
            }

            if (!\is_array($date) && is_string($date)) {
                $date = \explode(' - ', $date);
                $date = [
                    'from' => isset($date[0]) ? \trim($date[0]) : null,
                    'to' => isset($date[1]) ? \trim($date[1]) : null,
                ];
            }

            $from = $this->toTime($date['from'], 'Y-m-d');
            $to = $this->toTime($date['to'], 'Y-m-d');
            $column = $this->cleanField($column);

            if ($from && $to) {
                $this->whereDateBetween($column, [$from, $to]);
            } elseif ($from) {
                $this->whereDate($column, '=', $from);
            }
        }

        return $this;
    }

    protected function filterDatetimes(array $datetime): self
    {
        foreach ($datetime as $column => $date) {
            if (empty($date)) {
                continue;
            }

            if (!\is_array($date)) {
                $date = \explode(' - ', $date);
                $date = [
                    'from' => isset($date[0]) ? \trim($date[0]) : null,
                    'to' => isset($date[1]) ? \trim($date[1]) : null,
                ];
            }

            $from = $date['from'];
            $to = $date['to'];
            $to = $to ?: $from;

            $column = $this->cleanField($column);

            $from = $this->toTime($from, 'Y-m-d H:i:s', '00:00:00');
            $to = $this->toTime($to, 'Y-m-d H:i:s', '23:59:59');

            $this->whereBetween($column, [$from, $to]);
        }

        return $this;
    }

    protected function filterUnixTimes(array $unixtime): self
    {
        foreach ($unixtime as $column => $date) {
            if (empty($date)) {
                continue;
            }

            if (!\is_array($date)) {
                $date = \explode(' - ', $date);
                $date = [
                    'from' => isset($date[0]) ? \trim($date[0]) : null,
                    'to' => isset($date[1]) ? \trim($date[1]) : null,
                ];
            }

            $from = isset($date['from']) ? \trim($date['from']) : null;
            $to = isset($date['to']) ? \trim($date['to']) : null;
            $to = $to ?? $from;
            $column = $this->cleanField($column);

            if (!\is_numeric($from)) {
                $from = $this->toTime($from, null, '00:00:00');
                $from = $from && !is_string($from) ? $from->timestamp : null;
            }

            if (!\is_numeric($to)) {
                $to = $this->toTime($to, null, '23:59:59');
                $to = $to && !is_string($to) ? $to->timestamp : null;
            }

            $this->whereBetween($column, [$from, $to]);
        }

        return $this;
    }

    protected function filterRange(array $ranges): self
    {
        foreach ($ranges as $column => $date) {
            if (empty($date)) {
                continue;
            }

            if (!\is_array($date)) {
                $date = \explode(' - ', $date);
                $date = [
                    'from' => isset($date[0]) ? \trim($date[0]) : null,
                    'to' => isset($date[1]) ? \trim($date[1]) : null,
                ];
            }

            $from = $this->toTime($date['from']);
            $to = $this->toTime($date['to']);
            $column = $this->cleanField($column);

            if ($from && $to) {
                $this->whereBetween($column, [$from, $to]);
            } elseif ($from) {
                $this->where($column, $from);
            }
        }

        return $this;
    }

    protected function filterBetween(array $between): self
    {
        foreach ($between as $column => $date) {
            if (empty($date)) {
                continue;
            }

            if (!\is_array($date)) {
                $date = \explode(' - ', $date);
                $date = [
                    'from' => isset($date[0]) ? \trim($date[0]) : null,
                    'to' => isset($date[1]) ? \trim($date[1]) : null,
                ];
            }

            $from = $date['from'];
            $to = $date['to'];
            $column = $this->cleanField($column);

            if ($from && $to) {
                $this->whereBetween($column, [$from, $to]);
            } elseif ($from) {
                $this->where($column, $from);
            }
        }

        return $this;
    }

    protected function filterParse(array $filters): self
    {
        foreach ($filters as $filter) {
            $parseTree = (new Parser())->parse($filter);
            foreach ($parseTree->getPredicates() as $predicateInfo) {
                list($combinedBy, $predicate) = $predicateInfo;
                $op = ('=~' == $predicate->op) ? 'like' : $predicate->op;
                if ('OR' === $combinedBy) {
                    $this->orWhere((string) $predicate->left, $op, $predicate->right);
                } else {
                    $this->where((string) $predicate->left, $op, $predicate->right);
                }
            }
        }

        return $this;
    }

    /**
     * Add where condition for filters.
     *
     * @param string $column
     * @param string $value
     * @param string $condition
     */
    protected function addWhere($column, $value, $condition = '='): self
    {
        if (Str::contains($column, ':') && $this->getEloquent()) {
            (new FilterRelation($condition))($this->getEloquent(), $value, $column);

            return $this;
        }

        if (Str::contains($column, '|')) {
            $columns = \explode('|', $column);
            $this->where(function ($query) use ($columns, $value, $condition): void {
                foreach ($columns as $column) {
                    $query->orWhere($this->cleanField($column), $condition, $value);
                }
            });
        } else {
            $this->where($this->cleanField($column), $condition, $value);
        }

        return $this;
    }

    /**
     * Cleanup the give column.
     *
     * @param string $string Database column
     *
     * @return string Cleaned String
     */
    protected function cleanField($string)
    {
        if (Str::contains($string, '.')) {
            list($alias, $column) = explode('.', $string, 2);
            $column = $this->aliases[$column] ?? $column;

            return (string) $this->raw($alias . '.' . $this->wrap($column));
        }

        return (string) $this->raw($this->wrap($string));
    }

    /**
     * Convert time to proper format.
     *
     * @param string     $time
     * @param string     $format
     * @param null|mixed $prefix
     *
     * @return null|\Illuminate\Support\Carbon|string
     */
    protected function toTime($time, $format = null, $prefix = null)
    {
        if (empty($time)) {
            return null;
        }

        if (Str::contains($format, ':')) {
            $time = Str::contains($time, ':') ? $time : $time . ' ' . $prefix;
        }

        return carbon(\trim($time), $format);
    }
}
