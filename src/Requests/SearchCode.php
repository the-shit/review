<?php

namespace TheShit\Review\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class SearchCode extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $searchQuery,
        private readonly string $repo,
        private readonly int $perPage = 10,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/search/code';
    }

    protected function defaultQuery(): array
    {
        return [
            'q' => "{$this->searchQuery} repo:{$this->repo}",
            'per_page' => $this->perPage,
        ];
    }
}
