<?php

namespace Codewave\OpenTelemetry\Magento\Trace;

use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SDK\Trace\AutoRootSpan as Base;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Incubating\Attributes\HttpIncubatingAttributes;
use OpenTelemetry\SemConv\Version;
use Psr\Http\Message\ServerRequestInterface;

class AutoRootSpan extends Base
{
    /**
     * @psalm-suppress ArgumentTypeCoercion
     *
     * @internal
     */
    public static function create(ServerRequestInterface $request): void
    {
        $tracer = Globals::tracerProvider()->getTracer(
            'io.opentelemetry.php.auto-root-span',
            null,
            Version::VERSION_1_25_0->url(),
        );
        $parent = Globals::propagator()->extract($request->getHeaders());
        $startTime = array_key_exists('REQUEST_TIME_FLOAT', $request->getServerParams())
            ? $request->getServerParams()['REQUEST_TIME_FLOAT']
            : (int) microtime(true);
        $span = $tracer->spanBuilder($request->getMethod().' '.$request->getUri()->getPath())
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setStartTimestamp((int) ($startTime * ClockInterface::NANOS_PER_SECOND))
            ->setParent($parent)
            ->setAttribute(UrlAttributes::URL_FULL, (string) $request->getUri())
            ->setAttribute(HttpAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
            ->setAttribute(HttpIncubatingAttributes::HTTP_REQUEST_BODY_SIZE, $request->getHeaderLine('Content-Length'))
            ->setAttribute(UserAgentAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine('User-Agent'))
            ->setAttribute(ServerAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
            ->setAttribute(ServerAttributes::SERVER_PORT, $request->getUri()->getPort())
            ->setAttribute(UrlAttributes::URL_SCHEME, $request->getUri()->getScheme())
            ->setAttribute(UrlAttributes::URL_PATH, $request->getUri()->getPath())
            ->startSpan();
        Context::storage()->attach($span->storeInContext($parent));
    }
}
