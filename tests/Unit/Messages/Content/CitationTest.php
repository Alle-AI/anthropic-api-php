<?php

declare(strict_types=1);

namespace AlleAI\Anthropic\Tests\Unit\Messages\Content;

use AlleAI\Anthropic\Messages\Content\Citation;
use AlleAI\Anthropic\Messages\Content\ContentBlockFactory;
use AlleAI\Anthropic\Messages\Content\TextBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Citation::class)]
#[CoversClass(TextBlock::class)]
#[CoversClass(ContentBlockFactory::class)]
final class CitationTest extends TestCase
{
    public function testTextBlockSerializesCitations(): void
    {
        $cite = Citation::fromArray([
            'type' => 'page_location',
            'cited_text' => 'foo bar',
            'document_index' => 0,
            'document_title' => 'Doc',
            'start_page_number' => 1,
            'end_page_number' => 1,
        ]);
        $block = TextBlock::of('Per the doc')->withCitations([$cite]);
        $arr = $block->toArray();
        self::assertSame('Per the doc', $arr['text']);
        self::assertIsArray($arr['citations']);
        self::assertIsArray($arr['citations'][0]);
        self::assertSame('page_location', $arr['citations'][0]['type']);
        self::assertSame('foo bar', $arr['citations'][0]['cited_text']);
    }

    public function testFactoryParsesCitationsOnTextBlock(): void
    {
        $block = ContentBlockFactory::fromArray([
            'type' => 'text',
            'text' => 'See doc',
            'citations' => [
                ['type' => 'char_location', 'cited_text' => 'x', 'document_index' => 2, 'document_title' => 'T'],
            ],
        ]);
        self::assertInstanceOf(TextBlock::class, $block);
        self::assertCount(1, $block->citations);
        self::assertSame('char_location', $block->citations[0]->type);
        self::assertSame(2, $block->citations[0]->documentIndex);
    }
}
