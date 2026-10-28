<?php

declare(strict_types=1);

namespace SanadAi\Services;

use SanadAi\Http\Input;
use SanadAi\Http\NotFoundException;
use SanadAi\Http\Request;
use SanadAi\Http\Response;
use SanadAi\Tenancy\TenantContext;

final class ServiceController
{
    public function __construct(
        private readonly ServiceManager $manager,
        private readonly MinistryRepository $ministries,
        private readonly TenantContext $tenantContext,
        private readonly Response $response,
    ) {
    }

    /** GET /api/services?q=&status=&limit=&offset= */
    public function index(Request $request): void
    {
        [$limit, $offset] = Input::pagination($request);

        $result = $this->manager->search(
            tenantId: $this->tenantContext->require(),
            query: Input::queryString($request, 'q'),
            status: Input::queryString($request, 'status'),
            limit: $limit,
            offset: $offset,
        );

        $ministries = $this->ministries->allById();

        $this->response->json([
            'success' => true,
            'data' => array_map(
                fn (Service $service): array => $this->serialize($service, $ministries),
                $result['items']
            ),
            'meta' => [
                'total' => $result['total'],
                'limit' => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    /** GET /api/ministries */
    public function ministries(Request $request): void
    {
        $this->response->json([
            'success' => true,
            'data' => array_map(
                static fn (array $m): array => [
                    'public_id' => $m['public_id'],
                    'code' => $m['code'],
                    'name_ar' => $m['name_ar'],
                    'name_en' => $m['name_en'],
                ],
                $this->ministries->allActive()
            ),
        ]);
    }

    /** GET /api/services/{publicId} */
    public function show(Request $request, string $publicId): void
    {
        $service = $this->manager->find($this->tenantContext->require(), $publicId);

        if ($service === null) {
            throw new NotFoundException('Service not found.');
        }

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($service, $this->ministries->allById()),
        ]);
    }

    /** POST /api/services */
    public function store(Request $request): void
    {
        $data = $request->json();

        $service = $this->manager->create(
            tenantId: $this->tenantContext->require(),
            ministryId: $this->ministryId(Input::nullableString($data, 'ministry_public_id')),
            nameAr: Input::string($data, 'name_ar'),
            nameEn: Input::nullableString($data, 'name_en'),
            descriptionAr: Input::nullableString($data, 'description_ar'),
            descriptionEn: Input::nullableString($data, 'description_en'),
            officialFeeBaisa: Input::int($data, 'official_fee_baisa'),
            officeFeeBaisa: Input::int($data, 'office_fee_baisa'),
            targetSlaMinutes: Input::nullableInt($data, 'target_sla_minutes'),
        );

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($service, $this->ministries->allById()),
        ], 201);
    }

    /**
     * PUT /api/services/{publicId}
     *
     * Absent fields keep their value; null / "" clears optional ones.
     */
    public function update(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();
        $data = $request->json();

        $existing = $this->manager->find($tenantId, $publicId);

        if ($existing === null) {
            throw new NotFoundException('Service not found.');
        }

        $optional = static fn (string $key, ?string $current): ?string =>
            array_key_exists($key, $data) ? Input::nullableString($data, $key) : $current;

        $service = $this->manager->update(
            tenantId: $tenantId,
            publicId: $publicId,
            ministryId: array_key_exists('ministry_public_id', $data)
                ? $this->ministryId(Input::nullableString($data, 'ministry_public_id'))
                : $existing->ministryId,
            nameAr: Input::string($data, 'name_ar', $existing->nameAr),
            nameEn: $optional('name_en', $existing->nameEn),
            descriptionAr: $optional('description_ar', $existing->descriptionAr),
            descriptionEn: $optional('description_en', $existing->descriptionEn),
            officialFeeBaisa: Input::int($data, 'official_fee_baisa', $existing->officialFeeBaisa),
            officeFeeBaisa: Input::int($data, 'office_fee_baisa', $existing->officeFeeBaisa),
            targetSlaMinutes: array_key_exists('target_sla_minutes', $data)
                ? Input::nullableInt($data, 'target_sla_minutes')
                : $existing->targetSlaMinutes,
            status: Input::string($data, 'status', $existing->status),
        );

        $this->response->json([
            'success' => true,
            'data' => $this->serialize($service, $this->ministries->allById()),
        ]);
    }

    /** DELETE /api/services/{publicId} (soft: marks inactive) */
    public function destroy(Request $request, string $publicId): void
    {
        $tenantId = $this->tenantContext->require();

        if ($this->manager->find($tenantId, $publicId) === null) {
            throw new NotFoundException('Service not found.');
        }

        $this->manager->delete($tenantId, $publicId);

        $this->response->json([
            'success' => true,
            'message' => 'Service deactivated.',
        ]);
    }

    private function ministryId(?string $publicId): ?int
    {
        if ($publicId === null) {
            return null;
        }

        $ministry = $this->ministries->findByPublicId($publicId);

        if ($ministry === null) {
            throw new \InvalidArgumentException('Ministry not found or inactive.');
        }

        return $ministry['id'];
    }

    /**
     * @param array<int, array{id:int, public_id:string, name_ar:string, name_en:?string, code:?string}> $ministries
     * @return array<string, mixed>
     */
    private function serialize(Service $service, array $ministries): array
    {
        $ministry = $service->ministryId !== null
            ? ($ministries[$service->ministryId] ?? null)
            : null;

        return [
            'public_id' => $service->publicId,
            'ministry' => $ministry === null ? null : [
                'public_id' => $ministry['public_id'],
                'code' => $ministry['code'],
                'name_ar' => $ministry['name_ar'],
                'name_en' => $ministry['name_en'],
            ],
            'name_ar' => $service->nameAr,
            'name_en' => $service->nameEn,
            'description_ar' => $service->descriptionAr,
            'description_en' => $service->descriptionEn,
            'official_fee_baisa' => $service->officialFeeBaisa,
            'office_fee_baisa' => $service->officeFeeBaisa,
            'target_sla_minutes' => $service->targetSlaMinutes,
            'status' => $service->status,
            'created_at' => $service->createdAt,
            'updated_at' => $service->updatedAt,
        ];
    }
}
