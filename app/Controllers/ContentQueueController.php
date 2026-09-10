<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\ContentQueue;
use App\Models\Job;

final class ContentQueueController
{
    public function __construct(
        private readonly Job $jobModel,
    ) {
    }

    public function index(Request $request): Response
    {
        $conditions = array_intersect_key($request->query, array_flip(['site_id', 'status']));

        return Response::json([
            'content_queue' => (new ContentQueue($request->accountId()))->all($conditions),
        ]);
    }

    /**
     * The human review gate: ready_for_review -> approved, then enqueues
     * the publish job. This is the one manual step in an otherwise fully
     * automated pipeline (CLAUDE.md Phase 2 done-when).
     */
    public function approve(Request $request, array $params): Response
    {
        $id = (int) $params['id'];
        $contentQueue = new ContentQueue($request->accountId());
        $row = $contentQueue->find($id);

        if ($row === null) {
            return Response::error('Not found', 404);
        }

        if ($row['status'] !== 'ready_for_review') {
            return Response::error("Cannot approve a row with status '{$row['status']}'", 422);
        }

        $contentQueue->update($id, ['status' => 'approved']);

        $this->jobModel->enqueue('publish_content', [
            'content_queue_id' => $id,
            'account_id' => $request->accountId(),
            'site_id' => (int) $row['site_id'],
        ]);

        return Response::json(['id' => $id, 'status' => 'approved']);
    }
}
