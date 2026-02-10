<?php

use GladeHQ\LaravelEventLens\Models\EventLog;
use GladeHQ\LaravelEventLens\Concerns\HasEventLens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

// Test model that uses the HasEventLens trait
class TrackableOrder extends Model {
    use HasEventLens;

    protected $table = 'trackable_orders';
    protected $guarded = [];
}

// Event that carries a model
class OrderPlacedEvent {
    public TrackableOrder $order;

    public function __construct(TrackableOrder $order)
    {
        $this->order = $order;
    }
}

// Event without model
class SimpleEvent {
    public string $message = 'hello';
}

beforeEach(function () {
    Config::set('event-lens.enabled', true);
    Config::set('event-lens.sampling_rate', 1.0);
    Config::set('event-lens.namespaces', ['*']);

    Schema::create('trackable_orders', function (Blueprint $table) {
        $table->id();
        $table->string('status')->default('pending');
        $table->timestamps();
    });

    EventLog::truncate();
});

afterEach(function () {
    Schema::dropIfExists('trackable_orders');
});

it('auto-detects model from event payload', function () {
    $order = TrackableOrder::create(['status' => 'pending']);

    Event::listen(OrderPlacedEvent::class, fn () => true);
    Event::dispatch(new OrderPlacedEvent($order));

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    $log = EventLog::where('model_type', TrackableOrder::class)->first();
    expect($log)->not->toBeNull();
    expect($log->model_type)->toBe(TrackableOrder::class);
    expect((int) $log->model_id)->toBe($order->id);
});

it('stores null for events without models', function () {
    Event::listen(SimpleEvent::class, fn () => true);
    Event::dispatch(new SimpleEvent());

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    $log = EventLog::whereNull('model_type')->first();
    expect($log)->not->toBeNull();
    expect($log->model_type)->toBeNull();
    expect($log->model_id)->toBeNull();
});

it('model eventLogs() returns correct records', function () {
    $order = TrackableOrder::create(['status' => 'pending']);

    Event::listen(OrderPlacedEvent::class, fn () => true);
    Event::dispatch(new OrderPlacedEvent($order));

    app(\GladeHQ\LaravelEventLens\Services\EventLensBuffer::class)->flush();

    $logs = $order->eventLogs;
    expect($logs->count())->toBeGreaterThanOrEqual(1);
    expect($logs->first()->event_name)->toBe(OrderPlacedEvent::class);
});
