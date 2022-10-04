<?php

namespace OwenIt\Auditing\Drivers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\AuditDriver;

class Database implements AuditDriver
{
    /**
     * {@inheritdoc}
     */
    public function audit(Auditable $model): Collection
    {
        $implementation = Config::get('audit.implementation', \OwenIt\Auditing\Models\Audit::class);

        $data = $model->toAudit();

        $createData = $this->splitDataToCreate($data);
        $createdIds = $this->createAudits($createData, $implementation);

        $instance = new $implementation;
        return $instance->query()->whereIn($instance->getKeyName(), $createdIds)->get();
    }

    /**
     * {@inheritdoc}
     */
    public function prune(Auditable $model): bool
    {
        if (($threshold = $model->getAuditThreshold()) > 0) {
            $forRemoval = $model->audits()
                ->latest()
                ->get()
                ->slice($threshold)
                ->pluck('id');

            if (!$forRemoval->isEmpty()) {
                return $model->audits()
                    ->whereIn('id', $forRemoval)
                    ->delete() > 0;
            }
        }

        return false;
    }

    /**
     * Take old and new values and split them to be able to
     * get a single DB entry per attribute.
     *
     * @param array $data
     * @return array
     */
    private function splitDataToCreate(array $data): array
    {
        $oldValues = Arr::pull($data, 'old_values');
        $newValues = Arr::pull($data, 'new_values');

        $createData = [];

        foreach ($oldValues as $attribute => $value) {
            $createData[] = array_merge([
                'attribute' => $attribute,
                'old_value' => $value,
                'new_value' => Arr::pull($newValues, $attribute),
            ], $data);
        }

        foreach ($newValues as $attribute => $value) {
            $createData[] = array_merge([
                'attribute' => $attribute,
                'old_value' => Arr::pull($oldValues, $attribute),
                'new_value' => $value,
            ], $data);
        }

        return $createData;
    }

    /**
     * Create audits and return created IDs.
     *
     * @param array $createData
     * @param $implementation
     * @return array
     */
    private function createAudits(array $createData, $implementation): array
    {
        $ids = [];

        foreach ($createData as $create) {
            /** @var Model $created */
            $created = call_user_func([$implementation, 'create'], $create);

            if ($created) {
                $ids[] = $created->getKey();
            }
        }

        return $ids;
    }
}
