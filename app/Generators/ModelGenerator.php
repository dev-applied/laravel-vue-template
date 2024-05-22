<?php

namespace App\Generators;

use Based\TypeScript\Definitions\TypeScriptProperty;
use Based\TypeScript\Definitions\TypeScriptType;
use Based\TypeScript\Generators\ModelGenerator as BaseGenerator;
use Doctrine\DBAL\Schema\Column;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Throwable;

class ModelGenerator extends BaseGenerator
{
    protected function getAccessors(): string
    {
        $accessors = parent::getAccessors();
        return $accessors . PHP_EOL . '        ' . $this->getMethods()
            ->filter(fn (ReflectionMethod $method) => (string) $method->getReturnType() === Attribute::class)
            ->mapWithKeys(function (ReflectionMethod $method) {
                return [$method->getName() => $method];
            })
            ->reject(function (ReflectionMethod $method, string $property) {
                return $this->columns->contains(fn (Column $column) => $column->getName() == $property);
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
            ->join(PHP_EOL . '        ');
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
}
