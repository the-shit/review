<?php

namespace TheShit\Review\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetFileContents extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $owner,
        private readonly string $repo,
        private readonly string $path,
        private readonly ?string $ref = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return "/repos/{$this->owner}/{$this->repo}/contents/{$this->path}";
    }

    protected function defaultQuery(): array
    {
        return array_filter([
            'ref' => $this->ref,
        ], fn ($v) => $v !== null);
    }
}
