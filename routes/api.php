<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;

// User Microservice API Routes - VRAIE syntaxe Orion
Orion::resource('users', UserController::class);

// Microservice Health Check
Route::get('/health', function () {
    return response()->json([
        'service' => 'user-microservice',
        'status' => 'healthy',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'database' => \DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'features' => [
            'orion_crud' => 'enabled',
            'event_publishing' => 'enabled',
            'service_communication' => 'enabled'
        ]
    ]);
});

// Internal webhook endpoint for receiving events from other services
Route::post('/webhooks/{service}-events', function ($service) {
    $payload = request()->all();
    
    \Log::info("Received webhook from {$service}", $payload);
    
    // Process events from other microservices
    switch ($payload['event'] ?? '') {
        case 'order_created':
            // Handle order created event
            break;
        case 'payment_completed':
            // Handle payment completed event
            break;
        default:
            \Log::info("Unknown event type: " . ($payload['event'] ?? 'none'));
    }
    
    return response()->json(['status' => 'received']);
});

// Service discovery endpoint
Route::get('/info', function () {
    return response()->json([
        'service_name' => 'user-microservice',
        'capabilities' => [
            'user_management',
            'user_authentication', 
            'user_search_and_filtering',
            'event_publishing'
        ],
        'endpoints' => [
            'GET /api/users' => 'List users with search, filter, sort, pagination',
            'GET /api/users/{id}' => 'Get specific user',
            'POST /api/users' => 'Create new user',
            'PUT /api/users/{id}' => 'Update user (full)',
            'PATCH /api/users/{id}' => 'Update user (partial)',
            'DELETE /api/users/{id}' => 'Delete user'
        ],
        'events_published' => [
            'user.created',
            'user.updated', 
            'user.deleted'
        ],
        'events_consumed' => [
            'order.created',
            'payment.completed'
        ]
    ]);
});