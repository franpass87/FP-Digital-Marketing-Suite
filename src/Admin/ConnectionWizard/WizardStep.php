<?php

declare(strict_types=1);

namespace FP\DMS\Admin\ConnectionWizard;

/**
 * Interface for connection wizard steps.
 */
interface WizardStep
{
    /**
     * Get unique step identifier.
     */
    public function getId(): string;

    /**
     * Get step title for display.
     */
    public function getTitle(): string;

    /**
     * Get step description/subtitle.
     */
    public function getDescription(): string;

    /**
     * Render step HTML content.
     *
     * @param array $data Current wizard data
     */
    public function render(array $data): string;

    /**
     * Validate step data.
     *
     * @param array $data Data to validate
     * @return array{valid: bool, errors?: array} Validation result
     */
    public function validate(array $data): array;

    /**
     * Get help content for this step.
     *
     * @return array{title?: string, content?: string, links?: array}
     */
    public function getHelp(): array;

    /**
     * Check if this step can be skipped.
     */
    public function isSkippable(): bool;

    /**
     * Process step data before moving to next step.
     *
     * @param array $data Step data
     * @return array Processed data
     */
    public function process(array $data): array;
}
