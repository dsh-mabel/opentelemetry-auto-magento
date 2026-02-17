<?php

declare(strict_types=1);

namespace Codewave\OpenTelemetry\Magento\Hooks;

use Magento\Framework\App\Action\Action;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

class ActionHook
{
    use MagentoHookTrait;

    protected function hookExecute(): bool
    {
        return hook(
            Action::class,
            'dispatch',
            pre: function (Action $action, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                $request = $params[0];
                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder(sprintf('Action %s', $request->getFullActionName() ?: 'unknown'))
                    ->setAttribute(CodeAttributes::CODE_FUNCTION_NAME, $function)
                    ->setAttribute(CodeAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(CodeAttributes::CODE_LINE_NUMBER, $lineno);

                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));

                return $params;

            },
            post: function (Action $action, array $params, $returnValue, ?Throwable $exception): void {
                $this->endSpan($exception);
            }
        );
    }
}
