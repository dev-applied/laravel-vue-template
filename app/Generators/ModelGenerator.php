<?php

namespace App\Generators;

use Based\TypeScript\Definitions\TypeScriptProperty;
use Based\TypeScript\Definitions\TypeScriptType;
use Based\TypeScript\Generators\ModelGenerator as BaseGenerator;
use Doctrine\DBAL\Types\Types;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Str;
use Throwable;

class ModelGenerator extends BaseGenerator
{

    // Intentionally blocking the parent constructor
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {
    }

    protected function boot(): void
    {
        $this->model = $this->reflection->newInstance();

        $schema = Schema::connection($this->model->getConnection()->getName());

        $this->columns = collect($schema->getColumns($this->model->getTable()));
    }

    protected function getProperties(): string
    {
        return $this->columns->map(function ($column) {
            return (string) new TypeScriptProperty(
                name: $column['name'],
                types: $this->getPropertyType($column['type']),
                nullable: $column['nullable']
            );
        })
            ->join(PHP_EOL.'        ');
    }

    protected function getAccessors(): string
    {
        $relationsToSkip =  $this->getRelationMethods()
            ->map(function (ReflectionMethod $method) {
                return Str::snake($method->getName());
            });

        $accessors = $this->getMethods()
            ->filter(fn (ReflectionMethod $method) => Str::startsWith($method->getName(), 'get'))
            ->filter(fn (ReflectionMethod $method) => Str::endsWith($method->getName(), 'Attribute'))
            ->mapWithKeys(function (ReflectionMethod $method) {
                $property = (string) Str::of($method->getName())
                    ->between('get', 'Attribute')
                    ->snake();

                return [$property => $method];
            })
            ->reject(function (ReflectionMethod $method, string $property) {
                return $this->columns->contains(fn (array $column) => $column['name'] == $property);
            })
            ->reject(function (ReflectionMethod $method, string $property) use ($relationsToSkip) {
                return $relationsToSkip->contains($property);
            })
            ->map(function (ReflectionMethod $method, string $property) {
                return (string) new TypeScriptProperty(
                    name: $property,
                    types: TypeScriptType::fromMethod($method),
                    optional: true,
                    readonly: true
                );
            })
            ->join(PHP_EOL . '        ');

        return $accessors.PHP_EOL.'        '.$this->getMethods()
                ->filter(fn(ReflectionMethod $method) => (string) $method->getReturnType() === Attribute::class)
                ->mapWithKeys(function (ReflectionMethod $method) {
                    return [$method->getName() => $method];
                })
                ->reject(function (ReflectionMethod $method, string $property) {
                    return $this->columns->contains(fn($column) => $column['name'] == $property);
                })
                ->map(function (ReflectionMethod $method, string $property) {
                    $method->setAccessible(true);
                    $attribute = $method->invoke($method->getDeclaringClass()->newInstance());
                    if (!$attribute->get) {
                        return null;
                    }

                    return (string) new TypeScriptProperty(
                        name: \Str::snake($property),
                        types: TypeScriptType::fromMethod(new ReflectionMethod($attribute, 'get')),
                        optional: true,
                        readonly: true
                    );
                })
                ->filter()
                ->join(PHP_EOL.'        ');
    }

    protected function getRelationMethods(): Collection
    {
        return $this->getMethods()
            ->filter(function (ReflectionMethod $method) {
                try {
                    return $method->invoke($this->model) instanceof Relation;
                } catch (Throwable) {
                    return false;
                }
            });
    }

    protected function getPropertyType(string $type): string|array
    {
        $type = preg_replace('/\(\d+(,\d+)*\)/', '', $type);
        return match ($type) {
            Types::ASCII_STRING,
            Types::TEXT,
            Types::STRING,
            Types::GUID,
            Types::DATETIME_IMMUTABLE,
            Types::DATETIME_MUTABLE,
            Types::DATETIMETZ_MUTABLE,
            Types::DATEINTERVAL,
            Types::BINARY,
            Types::BLOB,
            Types::DATE_MUTABLE,
            Types::DATE_IMMUTABLE,
            Types::DATETIMETZ_IMMUTABLE,
            'nvarchar(max)',
            'nvarchar' => TypeScriptType::STRING,
            Types::BIGINT,
            Types::SMALLINT,
            Types::TIME_MUTABLE,
            Types::TIME_IMMUTABLE,
            Types::FLOAT,
            Types::DECIMAL,
            Types::INTEGER,
            'int' => TypeScriptType::NUMBER,
            Types::BOOLEAN,
            'bit' => TypeScriptType::BOOLEAN,
            Types::JSON,
            Types::SIMPLE_ARRAY => [TypeScriptType::array(), TypeScriptType::ANY],
            default => TypeScriptType::ANY,
        };
    }
}
