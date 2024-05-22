<?php

namespace App\Mixins;

use App\Models\User;
use Closure;
use Illuminate\Database\Schema\Blueprint;

/**
 * @mixin Blueprint
 */
class WhoDidItMixin
{
    public function whoDidIt(): Closure
    {
        return function () {
            $this->foreignIdFor(User::class, 'created_by_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $this->foreignIdFor(User::class, 'updated_by_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $this->foreignIdFor(User::class, 'deleted_by_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
        };
    }
}
