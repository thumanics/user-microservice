<?php

namespace App\Http\Controllers;

use App\Models\User;
use Orion\Http\Controllers\Controller;
use Orion\Concerns\DisableAuthorization;

class UserController extends Controller
{
    use DisableAuthorization;

    /**
     * Fully-qualified model class name
     */
    protected $model = User::class;

    /**
     * The attributes that are mass assignable.
     */
    protected function fillableFields(): array
    {
        return ['name', 'email', 'password'];
    }

    /**
     * The attributes that should be searchable.
     */
    protected function searchableFields(): array
    {
        return ['name', 'email'];
    }

    /**
     * The attributes that should be sortable.
     */
    protected function sortableFields(): array
    {
        return ['id', 'name', 'email', 'created_at', 'updated_at'];
    }

    /**
     * The attributes that should be filterable.
     */
    protected function filterableFields(): array
    {
        return ['id', 'name', 'email', 'created_at', 'updated_at'];
    }

    /**
     * The relations that are allowed to be included together with a resource.
     */
    public function includes(): array
    {
        return [];
    }

    /**
     * The number of resources to be displayed per page.
     */
    protected function perPage(): int
    {
        return 15;
    }

    /**
     * Transform the resource before saving.
     */
    protected function performStore(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity, array $attributes): void
    {
        if (isset($attributes['password'])) {
            $entity->password = bcrypt($attributes['password']);
        }

        // Microservice Event: User Created
        $this->publishEvent('user.created', [
            'user_id' => $entity->id ?? 'new',
            'email' => $attributes['email'],
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Transform the resource before updating.
     */
    protected function performUpdate(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity, array $attributes): void
    {
        if (isset($attributes['password'])) {
            $entity->password = bcrypt($attributes['password']);
        }

        // Microservice Event: User Updated
        $this->publishEvent('user.updated', [
            'user_id' => $entity->id,
            'changes' => $attributes,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Handle resource after successful creation.
     */
    protected function afterStore(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity): void
    {
        // Notify other microservices
        $this->notifyServices('user_created', $entity);
    }

    /**
     * Handle resource after successful update.
     */
    protected function afterUpdate(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity): void
    {
        // Notify other microservices
        $this->notifyServices('user_updated', $entity);
    }

    /**
     * Handle resource after successful deletion.
     */
    protected function afterDestroy(\Orion\Http\Requests\Request $request, \Illuminate\Database\Eloquent\Model $entity): void
    {
        // Notify other microservices
        $this->notifyServices('user_deleted', $entity);
    }

    /**
     * Publish event to message queue/event bus
     */
    private function publishEvent(string $eventType, array $data): void
    {
        // Log for now (will be replaced with Redis/RabbitMQ)
        \Log::info("Microservice Event: {$eventType}", $data);
        
        // TODO: Implement actual event publishing
        // Redis::publish($eventType, json_encode($data));
        // or RabbitMQ publish
    }

    /**
     * Notify other microservices via HTTP
     */
    private function notifyServices(string $event, \Illuminate\Database\Eloquent\Model $entity): void
    {
        $services = [
            'order-service' => env('ORDER_SERVICE_URL', 'http://order-service'),
            'notification-service' => env('NOTIFICATION_SERVICE_URL', 'http://notification-service'),
        ];

        foreach ($services as $serviceName => $serviceUrl) {
            try {
                \Http::timeout(5)->post("{$serviceUrl}/webhooks/user-events", [
                    'event' => $event,
                    'user_id' => $entity->id,
                    'user_data' => $entity->only(['id', 'name', 'email', 'created_at', 'updated_at']),
                    'timestamp' => now()->toISOString(),
                    'source_service' => 'user-service'
                ]);
            } catch (\Exception $e) {
                \Log::warning("Failed to notify {$serviceName}: " . $e->getMessage());
            }
        }
    }
}