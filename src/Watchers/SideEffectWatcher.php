<?php

namespace GladeHQ\LaravelEventLens\Watchers;

use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Mail\Events\MessageSending;

class SideEffectWatcher
{
    // Stack of counters: [ [queries, mails], [queries, mails] ]
    protected array $stack = [];
    
    protected bool $isListening = false;

    public function boot()
    {
        // One-time listener registration
        if ($this->isListening) return;
        
        Event::listen(QueryExecuted::class, function () {
            $this->increment('queries');
        });

        Event::listen(MessageSending::class, function () {
            $this->increment('mails');
        });
        
        $this->isListening = true;
    }

    public function start()
    {
        // Push new scope [queries => 0, mails => 0]
        $this->stack[] = ['queries' => 0, 'mails' => 0];
    }

    public function stop(): array
    {
        if (empty($this->stack)) {
            return ['queries' => 0, 'mails' => 0];
        }
        
        // Pop the top scope
        return array_pop($this->stack);
    }
    
    protected function increment(string $type)
    {
        if (empty($this->stack)) {
            return;
        }
        
        // Increment the *current* (top) scope
        // If we wanted to count side-effects for the parent too, we'd iterate.
        // But for "Self-time" vs "Total-time", usually request profiling is exclusive?
        // Let's keep it exclusive to this listener's execution frame for now.
        // Actually, if Listener A calls Event B, and B does a query, 
        // A's total time includes B. Should A's query count include B?
        // Usually, yes. "Inclusive".
        // Let's do Inclusive counting. 
        // We increment ALL items in the stack.
        
        foreach ($this->stack as &$scope) {
            $scope[$type]++;
        }
    }
}
