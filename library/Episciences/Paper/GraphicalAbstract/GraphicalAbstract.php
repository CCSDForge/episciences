<?php

declare(strict_types=1);

namespace Episciences\Paper\GraphicalAbstract;

/**
 * Illustration or graphical abstract attached to a paper version.
 *
 * Stored in PAPERS.DOCUMENT under database.current, as three sibling keys
 * (graphical_abstract_file, graphical_abstract_alt, graphical_abstract_license):
 * the "graphical_abstract" names are kept because they are part of the JSON
 * contract read by the API and the Next.js front-end.
 */
final class GraphicalAbstract
{
    /**
     * @param string $file file name in the paper's public documents directory
     * @param string|null $alt text alternative of the image (null for images uploaded before it was required)
     * @param string|null $license free-text license of the image
     */
    public function __construct(
        public readonly string  $file,
        public readonly ?string $alt = null,
        public readonly ?string $license = null
    )
    {
    }

    public function withFile(string $file): self
    {
        return new self($file, $this->alt, $this->license);
    }
}
