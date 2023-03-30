<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait WhoDidIt
{
    public static function bootWhoDidIt() {
        static::creating(function (Model $model) {
            if (Auth::id()) {
                $createdByColumn = $model->getCreatedByColumn();
                $updatedByColumn = $model->getUpdatedByColumn();
                $model->$createdByColumn = Auth::id();
                $model->$updatedByColumn = Auth::id();
            }
        });

        static::updating(function (Model $model) {
            if (Auth::id()) {
                $updatedByColumn = $model->getUpdatedByColumn();
                $model->$updatedByColumn = Auth::id();
            }
        });

        static::deleting(function (Model $model) {
            if (Auth::id()) {
                //check if the model is using soft deletes
                if (method_exists($model, 'trashed')) {
                    $deletedByColumn = $model->getDeletedByColumn();
                    $model->$deletedByColumn = Auth::id();
                    $model->save();
                }
            }
        });
    }

    public function created_by(): BelongsTo
    {
        return $this->belongsTo(User::class, $this->getCreatedByColumn());
    }

    public function updated_by(): BelongsTo
    {
        return $this->belongsTo(User::class, $this->getUpdatedByColumn());
    }

    public function deleted_by(): BelongsTo
    {
        return $this->belongsTo(User::class, $this->getDeletedByColumn());
    }

    public function getCreatedByColumn() {
        $createdByColumn = config('who-did-it.created_by_field');
        if (isset($this->whoDidIt['created_by_field'])) {
            $createdByColumn = $this->whoDidIt['created_by_field'];
        }

        return $createdByColumn;
    }

    public function getUpdatedByColumn() {
        $updatedByColumn = config('who-did-it.updated_by_field');
        if (isset($this->whoDidIt['updated_by_field'])) {
            $updatedByColumn = $this->whoDidIt['updated_by_field'];
        }

        return $updatedByColumn;
    }

    public function getDeletedByColumn() {
        $deletedByColumn = config('who-did-it.deleted_by_field');
        if (isset($this->whoDidIt['deleted_by_field'])) {
            $deletedByColumn = $this->whoDidIt['deleted_by_field'];
        }

        return $deletedByColumn;
    }

}
