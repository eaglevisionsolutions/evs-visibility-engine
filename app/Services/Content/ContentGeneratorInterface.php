<?php

declare(strict_types=1);

namespace App\Services\Content;

/**
 * One provider today (Claude), written as an interface for the same reason
 * BillingProviderInterface and CompetitorDataProvider are: nothing outside
 * a concrete generator may reference a vendor SDK/API shape directly.
 */
interface ContentGeneratorInterface
{
    /**
     * @return array{
     *   seo_title: string, meta_desc: string, h1: string,
     *   outline: list<string>, content_html: string, slug: string,
     * }
     */
    public function generateDraft(string $keyword, string $siteDomain): array;
}
