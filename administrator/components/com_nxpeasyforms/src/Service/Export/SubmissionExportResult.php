<?php
declare(strict_types=1);

namespace Joomla\Component\Nxpeasyforms\Administrator\Service\Export;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Value object describing an export payload for submissions.
 *
 * @since 1.0.0
 */
final class SubmissionExportResult
{
    public function __construct(
        private readonly string $filename,
        private readonly string $contentType,
        private readonly string $contents
    ) {
    }

    /**
     * Get the recommended filename for the exported payload.
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Get the MIME type associated with the exported payload.
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Get the raw payload contents.
     */
    public function getContents(): string
    {
        return $this->contents;
    }
}
