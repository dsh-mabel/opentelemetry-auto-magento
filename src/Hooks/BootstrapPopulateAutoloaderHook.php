<?php

declare(strict_types=1);

namespace Codewave\OpenTelemetry\Magento\Hooks;

use Codewave\OpenTelemetry\Magento\Trace\AutoRootSpan;
use Codewave\OpenTelemetry\Magento\Trace\CliAutoRootSpan;
use Magento\Framework\App\Bootstrap;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

class BootstrapPopulateAutoloaderHook
{
    use MagentoHookTrait;

    private $rootSpanRegistered = false;

    protected function hookExecute(): bool
    {
        return hook(
            Bootstrap::class,
            'populateAutoloader',
            pre: function ($className, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
                $this->registerRootSpan();

                $builder = $this->instrumentation
                    ->tracer()
                    ->spanBuilder('Bootstrap::populateAutoloader')
                    ->setAttribute(CodeAttributes::CODE_FUNCTION_NAME, $function)
                    ->setAttribute(CodeAttributes::CODE_FILE_PATH, $filename)
                    ->setAttribute(CodeAttributes::CODE_LINE_NUMBER, $lineno);

                $parent = Context::getCurrent();

                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));

                return $params;

            },
            post: function ($className, array $params, $returnValue, ?Throwable $exception): void {
                $this->endSpan($exception);
            }
        );
    }

    private function registerRootSpan(): void
    {
        if ($this->rootSpanRegistered) {
            return;
        }

        if (! empty($_SERVER['REQUEST_METHOD'] ?? null)) {
            $request = AutoRootSpan::createRequest();

            if ($request) {
                $this->rootSpanRegistered = true;
                AutoRootSpan::create($request);
                AutoRootSpan::registerShutdownHandler();
            }

        }
        if (! empty($_SERVER['argv'] ?? null)) {
            $command = CliAutoRootSpan::createCommand();

            if ($command) {
                CliAutoRootSpan::create($command);
                CliAutoRootSpan::registerShutdownHandler();
            }
        }
    }
}
