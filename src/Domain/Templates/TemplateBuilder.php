<?php

declare(strict_types=1);

namespace FP\DMS\Domain\Templates;

final class TemplateBuilder
{
    /**
     * @var list<string>
     */
    private array $sections = [];

    public static function make(): self
    {
        return new self();
    }

    public function addSection(string $title, string $body, string $headingTag = 'h2'): self
    {
        $headingTag = in_array($headingTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $headingTag : 'h2';
        $section = '<div class="section">'
            . '<' . $headingTag . '>' . $title . '</' . $headingTag . '>'
            . $body
            . '</div>';
        $this->sections[] = $section;

        return $this;
    }

    /**
     * @param array<int,array{label:string,value:string}> $metrics
     */
    public function addKpiSection(string $title, array $metrics): self
    {
        $cards = '';
        foreach ($metrics as $metric) {
            if (! isset($metric['label'], $metric['value'])) {
                continue;
            }

            $cards .= '<div class="kpi"><span>' . $metric['label'] . '</span><strong>' . $metric['value'] . '</strong></div>';
        }

        if ($cards === '') {
            return $this;
        }

        $body = '<div class="kpi-grid">' . $cards . '</div>';

        return $this->addSection($title, $body);
    }

    public function addRawSection(string $html): self
    {
        if ($html !== '') {
            $this->sections[] = $html;
        }

        return $this;
    }

    public function build(): string
    {
        return implode("\n\n", $this->sections);
    }
}
