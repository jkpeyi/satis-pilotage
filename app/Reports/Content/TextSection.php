<?php

namespace App\Reports\Content;

/**
 * Paragraphs and bullet items replacing one {{variable}} of a template.
 */
final class TextSection
{
    /** @var list<array{type: 'p'|'li', text: string}> */
    private array $blocks = [];

    public function paragraph(string $text): self
    {
        $this->blocks[] = ['type' => 'p', 'text' => $text];

        return $this;
    }

    /**
     * @param  iterable<string>  $items
     */
    public function bullets(iterable $items): self
    {
        foreach ($items as $item) {
            $this->blocks[] = ['type' => 'li', 'text' => $item];
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->blocks === [];
    }

    /**
     * @return list<array{type: 'p'|'li', text: string}>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }
}
