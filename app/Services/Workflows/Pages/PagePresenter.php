<?php

namespace App\Services\Workflows\Pages;

use App\Enums\Workflows\PageAccess;
use App\Enums\Workflows\PageBlockType;
use App\Models\Workflows\WorkflowPage;
use App\Services\Workflows\WorkflowInterface;

/**
 * A page as the person using it receives it: its blocks, with each field
 * carrying the interface definition it fills in (type, choices, default,
 * whether it's required), and nothing about the workflow behind it: no
 * graph, no step names, no ids.
 *
 * A field whose input the workflow no longer has is left out rather than
 * shown as a control that submits to nothing.
 *
 * `branding` is the badge as it should render (see `PageBranding`), and
 * `theme.show_branding` is set to match it, so a page on a plan that can't
 * hide the badge shows it even when its theme says otherwise.
 */
class PagePresenter
{
    public function __construct(
        private readonly WorkflowInterface $interface,
        private readonly PageBranding $branding,
    ) {}

    /**
     * @param  array{blocks: array<int, array<string, mixed>>, theme: array<string, mixed>}  $content
     * @return array<string, mixed>
     */
    public function present(WorkflowPage $page, array $content, bool $locked = false): array
    {
        $branding = $this->branding->for($page, $content);

        $view = [
            'name' => $page->name,
            'description' => $page->description,
            'workspace_name' => $page->workspace?->name,
            'theme' => [...$content['theme'], 'show_branding' => $branding['show']],
            'branding' => $branding,
            'locked' => $locked,
            'requires_password' => $page->access === PageAccess::Password,
            'blocks' => [],
        ];

        if ($locked || $page->workflow === null || $page->workflow->trashed()) {
            return $view;
        }

        $fields = collect($this->interface->describe($page->workflow)['fields'])->keyBy('key')->all();
        $view['blocks'] = $this->withFields($content['blocks'], $fields);

        return $view;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private function withFields(array $blocks, array $fields): array
    {
        $presented = [];

        foreach ($blocks as $block) {
            if ($block['type'] === PageBlockType::Field->value) {
                $field = $fields[$block['props']['field_key']] ?? null;

                if ($field === null) {
                    continue;
                }

                $block['field'] = $field;
            }

            if (isset($block['children'])) {
                $block['children'] = $this->withFields($block['children'], $fields);
            }

            $presented[] = $block;
        }

        return $presented;
    }
}
