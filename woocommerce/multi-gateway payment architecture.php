<?php
declare(strict_types=1);

/**
 * Dummy multi-gateway payment architecture (UpStroke-style).
 * Run: php upstroke_payments_demo.php   (PHP 8.1+)
 * No real SDKs are used; each adapter fakes its provider's response.
 */

/* ============================================================
 * DOMAIN
 * ============================================================ */

final class Money
{
    public function __construct(private int $minorUnits, private string $currency) {}
    public function minorUnits(): int { return $this->minorUnits; }
    public function currency(): string { return $this->currency; }
    public function __toString(): string
    {
        return sprintf('%.2f %s', $this->minorUnits / 100, $this->currency);
    }
}

enum PaymentStatus: string
{
    case CREATED = 'created';
    case PROCESSING = 'processing';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case REQUIRES_ACTION = 'requires_action';
}

final class PaymentMethod
{
    public function __construct(
        public readonly string $id,
        public readonly string $gateway,
        public readonly string $type,
        public readonly string $providerReference,
        public readonly string $customerReference,
    ) {}
}

final class PaymentResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $providerPaymentId = null,
        public readonly ?string $actionUrl = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $errorCode = null,
    ) {}

    public function isSuccessful(): bool { return $this->status === PaymentStatus::SUCCEEDED; }
}

final class GatewayCapabilities
{
    public function __construct(
        public readonly bool $savedPaymentMethods = false,
        public readonly bool $offSessionPayments = false,
        public readonly bool $refunds = false,
        public readonly bool $partialRefunds = false,
        public readonly bool $subscriptions = false,
        public readonly bool $customerAuthentication = false,
    ) {}
}

/* ============================================================
 * PORTS (interfaces the core depends on)
 * ============================================================ */

interface PaymentGateway
{
    public function name(): string;
    public function capabilities(): GatewayCapabilities;
    public function charge(PaymentMethod $method, Money $amount): PaymentResult;
}

interface PaymentRefunds
{
    public function refund(string $providerPaymentId, Money $amount): bool;
}

interface PaymentWebhookHandler
{
    /** Normalize a provider payload into our internal event. */
    public function handle(array $payload): PaymentEvent;
}

final class PaymentEvent
{
    public function __construct(
        public readonly string $type,           // PaymentSucceeded, PaymentFailed, ...
        public readonly string $providerPaymentId,
        public readonly PaymentStatus $newStatus,
    ) {}
}

/* ============================================================
 * ADAPTERS (only these know provider terminology)
 * ============================================================ */

final class StripeGateway implements PaymentGateway, PaymentRefunds, PaymentWebhookHandler
{
    public function name(): string { return 'stripe'; }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(true, true, true, true, true, true);
    }

    public function charge(PaymentMethod $method, Money $amount): PaymentResult
    {
        // Real code: $stripe->paymentIntents->create([... 'off_session' => true, 'confirm' => true]);
        $fakeIntent = [
            'id' => 'pi_' . bin2hex(random_bytes(4)),
            'status' => $amount->minorUnits() > 50000 ? 'requires_action' : 'succeeded',
            'client_secret' => 'pi_secret_' . bin2hex(random_bytes(4)),
        ];

        return match ($fakeIntent['status']) {
            'succeeded' => new PaymentResult(PaymentStatus::SUCCEEDED, $fakeIntent['id']),
            'requires_action' => new PaymentResult(
                PaymentStatus::REQUIRES_ACTION, $fakeIntent['id'], clientSecret: $fakeIntent['client_secret']
            ),
            default => new PaymentResult(PaymentStatus::FAILED, $fakeIntent['id'], errorCode: 'card_declined'),
        };
    }

    public function refund(string $providerPaymentId, Money $amount): bool
    {
        echo "  [Stripe] refunding {$amount} on {$providerPaymentId}\n";
        return true;
    }

    public function handle(array $payload): PaymentEvent
    {
        // Stripe: {"type":"payment_intent.succeeded","data":{"object":{"id":"pi_..."}}}
        $status = $payload['type'] === 'payment_intent.succeeded'
            ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED;
        return new PaymentEvent(
            $status === PaymentStatus::SUCCEEDED ? 'PaymentSucceeded' : 'PaymentFailed',
            $payload['data']['object']['id'],
            $status,
        );
    }
}

final class PayPalGateway implements PaymentGateway, PaymentWebhookHandler
{
    public function name(): string { return 'paypal'; }

    public function capabilities(): GatewayCapabilities
    {
        return new GatewayCapabilities(true, true, true, false, true, false);
    }

    public function charge(PaymentMethod $method, Money $amount): PaymentResult
    {
        // Real code: create order using vault ID ($method->providerReference), then capture.
        return new PaymentResult(PaymentStatus::SUCCEEDED, 'PAYPAL-' . strtoupper(bin2hex(random_bytes(4))));
    }

    public function handle(array $payload): PaymentEvent
    {
        // PayPal: {"event_type":"PAYMENT.CAPTURE.COMPLETED","resource":{"id":"..."}}
        $ok = $payload['event_type'] === 'PAYMENT.CAPTURE.COMPLETED';
        return new PaymentEvent(
            $ok ? 'PaymentSucceeded' : 'PaymentFailed',
            $payload['resource']['id'],
            $ok ? PaymentStatus::SUCCEEDED : PaymentStatus::FAILED,
        );
    }
}

final class RazorpayGateway implements PaymentGateway
{
    public function name(): string { return 'razorpay'; }

    public function capabilities(): GatewayCapabilities
    {
        // Behaviour depends on tokenization / mandate rules, so off-session is false here
        // to demonstrate the capability check blocking a one-click upsell.
        return new GatewayCapabilities(true, false, true, true, true, true);
    }

    public function charge(PaymentMethod $method, Money $amount): PaymentResult
    {
        return new PaymentResult(PaymentStatus::PENDING, 'pay_' . bin2hex(random_bytes(4)));
    }
}

/* ============================================================
 * RESOLVER + REPOSITORY
 * ============================================================ */

final class UnsupportedGatewayException extends RuntimeException {}

final class PaymentGatewayResolver
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->name()] = $gateway;
    }

    public function resolve(string $name): PaymentGateway
    {
        return $this->gateways[$name]
            ?? throw new UnsupportedGatewayException("No gateway registered for '{$name}'");
    }
}

interface PaymentMethodRepository
{
    public function find(string $id): ?PaymentMethod;
    public function save(PaymentMethod $method): void;
}

final class InMemoryPaymentMethodRepository implements PaymentMethodRepository
{
    /** @var array<string, PaymentMethod> */
    private array $store = [];
    public function find(string $id): ?PaymentMethod { return $this->store[$id] ?? null; }
    public function save(PaymentMethod $method): void { $this->store[$method->id] = $method; }
}

/* ============================================================
 * STATE MACHINE
 * ============================================================ */

final class PaymentStateMachine
{
    private const TRANSITIONS = [
        'created'         => ['processing'],
        'processing'      => ['succeeded', 'failed', 'requires_action', 'pending'],
        'requires_action' => ['processing', 'failed'],
        'pending'         => ['succeeded', 'failed'],
        'succeeded'       => [],
        'failed'          => [],
    ];

    public function __construct(private PaymentStatus $current = PaymentStatus::CREATED) {}

    public function current(): PaymentStatus { return $this->current; }

    public function transitionTo(PaymentStatus $next): void
    {
        if (!in_array($next->value, self::TRANSITIONS[$this->current->value], true)) {
            throw new LogicException("Illegal transition {$this->current->value} -> {$next->value}");
        }
        $this->current = $next;
    }
}

/* ============================================================
 * APPLICATION SERVICES (never mention Stripe/PayPal/Razorpay)
 * ============================================================ */

final class PaymentService
{
    public function __construct(
        private PaymentMethodRepository $methods,
        private PaymentGatewayResolver $resolver,
    ) {}

    public function charge(string $paymentMethodId, Money $amount): array
    {
        $method = $this->methods->find($paymentMethodId)
            ?? throw new RuntimeException("Payment method {$paymentMethodId} not found");

        $gateway = $this->resolver->resolve($method->gateway);

        if (!$gateway->capabilities()->offSessionPayments) {
            return [new PaymentResult(PaymentStatus::FAILED, errorCode: 'off_session_unsupported'),
                    new PaymentStateMachine(PaymentStatus::FAILED)];
        }

        $sm = new PaymentStateMachine();
        $sm->transitionTo(PaymentStatus::PROCESSING);

        $result = $gateway->charge($method, $amount);
        $sm->transitionTo($result->status);

        return [$result, $sm];
    }
}

final class UpsellService
{
    public function __construct(private PaymentService $payments) {}

    public function acceptOffer(string $offerName, string $paymentMethodId, Money $price): void
    {
        echo "Customer accepted '{$offerName}' ({$price}) using payment method {$paymentMethodId}\n";

        [$result, $sm] = $this->payments->charge($paymentMethodId, $price);

        match ($result->status) {
            PaymentStatus::SUCCEEDED =>
                print("  -> PAID. Provider id: {$result->providerPaymentId}\n"),
            PaymentStatus::REQUIRES_ACTION =>
                print("  -> Needs customer authentication (secret: {$result->clientSecret}{$result->actionUrl})\n"),
            PaymentStatus::PENDING =>
                print("  -> Pending, waiting for webhook. Provider id: {$result->providerPaymentId}\n"),
            PaymentStatus::FAILED =>
                print("  -> Could not charge: {$result->errorCode}. Fall back to normal checkout.\n"),
            default => null,
        };
        echo "  state = {$sm->current()->value}\n\n";
    }
}

final class WebhookController
{
    public function __construct(private PaymentGatewayResolver $resolver) {}

    public function receive(string $gatewayName, array $payload): void
    {
        $gateway = $this->resolver->resolve($gatewayName);
        if (!$gateway instanceof PaymentWebhookHandler) {
            echo "  {$gatewayName} has no webhook handler\n";
            return;
        }
        $event = $gateway->handle($payload);
        echo "  Webhook from {$gatewayName} -> {$event->type} ({$event->providerPaymentId}), order updated\n";
    }
}

/* ============================================================
 * DEMO
 * ============================================================ */

$resolver = new PaymentGatewayResolver();
$resolver->register(new StripeGateway());
$resolver->register(new PayPalGateway());
$resolver->register(new RazorpayGateway());

$repo = new InMemoryPaymentMethodRepository();
$repo->save(new PaymentMethod('pm_internal_101', 'stripe',   'card',   'pm_ABC123', 'cus_XYZ789'));
$repo->save(new PaymentMethod('pm_internal_102', 'paypal',   'paypal', 'vault_ABC', 'payer_XYZ'));
$repo->save(new PaymentMethod('pm_internal_103', 'razorpay', 'card',   'token_RZP', 'cust_RZP'));

$upsell = new UpsellService(new PaymentService($repo, $resolver));

echo "=== Upsell flows ===\n";
$upsell->acceptOffer('Extra Warranty',   'pm_internal_101', new Money(1999,  'USD')); // Stripe success
$upsell->acceptOffer('Premium Bundle',   'pm_internal_101', new Money(99900, 'USD')); // Stripe 3DS
$upsell->acceptOffer('Gift Wrap',        'pm_internal_102', new Money(499,   'USD')); // PayPal success
$upsell->acceptOffer('Priority Support', 'pm_internal_103', new Money(2999,  'INR')); // Razorpay blocked

echo "=== Webhooks ===\n";
$hooks = new WebhookController($resolver);
$hooks->receive('stripe', ['type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_123']]]);
$hooks->receive('paypal', ['event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => ['id' => 'PAYPAL-999']]);
$hooks->receive('razorpay', []);

echo "\n=== Illegal state transition ===\n";
try {
    $sm = new PaymentStateMachine(PaymentStatus::SUCCEEDED);
    $sm->transitionTo(PaymentStatus::PROCESSING);
} catch (LogicException $e) {
    echo "  Caught: {$e->getMessage()}\n";
}
