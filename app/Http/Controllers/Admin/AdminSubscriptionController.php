<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSubscriptionStatusRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class AdminSubscriptionController extends Controller
{
    use ApiResponseTrait;

    #[OA\Get(
        path: '/api/admin/subscriptions',
        summary: 'List all subscriptions (admin)',
        tags: ['Admin - Subscriptions'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'plan', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subscriptions listed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 50);
            $search = $request->query('search');
            $status = $request->query('status');
            $planSlug = $request->query('plan');

            $subs = Subscription::with(['store', 'plan'])
                ->when($search, fn ($q, $s) => $q->where(fn ($w) => $w->whereHas('store', fn ($sq) => $sq->where('name', 'like', "%{$s}%"))))
                ->when($status, fn ($q, $st) => $q->where('status', $st))
                ->when($planSlug, fn ($q, $ps) => $q->whereHas('plan', fn ($pq) => $pq->where('slug', $ps)))
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return SubscriptionResource::collection($subs);
        } catch (Exception $e) {
            return $this->errorResponse('Error listing subscriptions: '.$e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/subscriptions/{id}',
        summary: 'Get a subscription with payments (admin)',
        tags: ['Admin - Subscriptions'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Subscription retrieved'),
            new OA\Response(response: 404, description: 'Subscription not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        try {
            $sub = Subscription::with(['store', 'plan', 'payments'])->find($id);

            if (! $sub) {
                return $this->notFoundResponse('Subscription not found');
            }

            return $this->successResponse(new SubscriptionResource($sub));
        } catch (Exception $e) {
            return $this->errorResponse('Error fetching subscription: '.$e->getMessage(), 500);
        }
    }

    #[OA\Patch(
        path: '/api/admin/subscriptions/{id}',
        summary: 'Update subscription status (admin)',
        tags: ['Admin - Subscriptions'],
        security: [['jwt' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                required: ['status'],
                properties: [
                    new OA\Property(
                        property: 'status',
                        type: 'string',
                        enum: ['active', 'trialing', 'past_due', 'cancelled', 'expired'],
                        example: 'active'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Subscription updated'),
            new OA\Response(response: 404, description: 'Subscription not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateSubscriptionStatusRequest $request, int $id): JsonResponse
    {
        try {
            $sub = Subscription::find($id);

            if (! $sub) {
                return $this->notFoundResponse('Subscription not found');
            }

            $validated = $request->validated();
            $sub->status = $validated['status'];

            if ($validated['status'] === 'cancelled' && ! $sub->cancelled_at) {
                $sub->cancelled_at = now();
            }

            $sub->save();

            return $this->successResponse(new SubscriptionResource($sub), 'Subscription updated');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors());
        } catch (Exception $e) {
            return $this->errorResponse('Error updating subscription: '.$e->getMessage(), 500);
        }
    }
}
