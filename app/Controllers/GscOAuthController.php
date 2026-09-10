<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Job;
use App\Models\Site;
use App\Services\Gsc\GoogleOAuthService;
use RuntimeException;

final class GscOAuthController
{
    public function __construct(
        private readonly GoogleOAuthService $oauth,
        private readonly Job $jobModel,
    ) {
    }

    public function connect(Request $request, array $params): Response
    {
        $siteId = (int) $params['id'];

        if ((new Site($request->accountId()))->find($siteId) === null) {
            return Response::error('Site not found', 404);
        }

        return Response::json(['auth_url' => $this->oauth->buildAuthUrl($request->accountId(), $siteId)]);
    }

    /**
     * Google redirects the browser here directly — no Bearer token exists
     * at this point, so this route is registered auth: false. Tenant
     * identity comes entirely from the signed `state` param instead
     * (see GoogleOAuthService::verifyState).
     */
    public function callback(Request $request): Response
    {
        $code = $request->query['code'] ?? null;
        $state = $request->query['state'] ?? null;

        if (!is_string($code) || !is_string($state)) {
            return Response::error('Missing code or state', 422);
        }

        try {
            ['account_id' => $accountId, 'site_id' => $siteId] = $this->oauth->verifyState($state);
            $tokens = $this->oauth->exchangeCode($code);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage(), 400);
        }

        $site = new Site($accountId);

        if ($site->find($siteId) === null) {
            return Response::error('Site not found', 404);
        }

        $site->update($siteId, [
            'gsc_refresh_token' => $tokens['refresh_token'],
            'gsc_connected_at' => date('Y-m-d H:i:s'),
        ]);

        $this->jobModel->enqueue('sync_gsc', ['account_id' => $accountId, 'site_id' => $siteId]);

        return Response::json(['connected' => true]);
    }

    public function sync(Request $request, array $params): Response
    {
        $siteId = (int) $params['id'];
        $site = (new Site($request->accountId()))->find($siteId);

        if ($site === null) {
            return Response::error('Site not found', 404);
        }

        if (empty($site['gsc_refresh_token'])) {
            return Response::error('Site is not connected to Google Search Console yet', 422);
        }

        $this->jobModel->enqueue('sync_gsc', ['account_id' => $request->accountId(), 'site_id' => $siteId]);

        return Response::json(['queued' => true]);
    }
}
