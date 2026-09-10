<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AccountService;
use App\Services\SiteService;
use RuntimeException;

final class SiteController
{
    public function __construct(
        private readonly SiteService $siteService,
        private readonly AccountService $accountService,
    ) {
    }

    public function index(Request $request): Response
    {
        return Response::json(['sites' => $this->siteService->list($request->accountId())]);
    }

    public function store(Request $request): Response
    {
        $body = $request->body;

        if (empty($body['domain'])) {
            return Response::error('Missing required field: domain', 422);
        }

        $account = $this->accountService->get($request->accountId());

        try {
            $id = $this->siteService->create(
                $request->accountId(),
                $account['plan_key'],
                $body['domain'],
                array_intersect_key($body, array_flip([
                    'wp_url', 'wp_username', 'wp_app_password', 'gsc_property', 'ga4_property_id',
                ])),
            );
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 422);
        }

        return Response::json(['id' => $id], 201);
    }
}
