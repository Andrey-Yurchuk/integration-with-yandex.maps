<?php

namespace Tests\Unit\Services\YandexMaps;

use App\Exceptions\YandexMaps\ChangedSchemaException;
use App\Exceptions\YandexMaps\UnavailableException;
use App\Services\YandexMaps\PageStateExtractor;
use Tests\Fixtures\YandexMaps\FixturePage;
use Tests\TestCase;

final class PageStateExtractorTest extends TestCase
{
    private PageStateExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extractor = new PageStateExtractor;
    }

    public function test_extracts_organization_item_and_reviews_from_state_view_html(): void
    {
        $html = FixturePage::html(FixturePage::organizationState());
        $state = $this->extractor->extractState($html);
        $item = $this->extractor->organizationItem($state);
        $reviews = $this->extractor->reviews($state);

        $this->assertSame('Cafe Pushkin', $item['title']);
        $this->assertCount(2, $reviews);
        $this->assertSame('review-1', $reviews[0]['reviewId']);
    }

    public function test_throws_changed_schema_exception_when_state_view_is_missing(): void
    {
        $this->expectException(ChangedSchemaException::class);

        $this->extractor->extractState('<html><body>no state</body></html>');
    }

    public function test_throws_unavailable_exception_when_organization_is_not_found(): void
    {
        $state = json_decode(
            (string) file_get_contents(base_path('tests/Fixtures/YandexMaps/not_found_state.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->expectException(UnavailableException::class);

        $this->extractor->organizationItem($state);
    }
}
