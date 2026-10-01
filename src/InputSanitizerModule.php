<?php

declare(strict_types=1);

namespace Wobqqq\AegisInputSanitizer;

use Override;
use Wobqqq\Aegis\Checks\CheckResult;
use Wobqqq\Aegis\Contracts\Module;
use Wobqqq\Aegis\Settings\Field;
use Wobqqq\AegisInputSanitizer\Enums\Category;
use Wobqqq\AegisInputSanitizer\Rules\CompilablePattern;
use Wobqqq\AegisInputSanitizer\Rules\ExistingView;
use Wobqqq\AegisInputSanitizer\Settings\InputSanitizerSettings;
use Wobqqq\AegisInputSanitizer\Support\Message;

final class InputSanitizerModule implements Module
{
    public const string KEY = 'input-sanitizer';

    #[Override]
    public function key(): string
    {
        return self::KEY;
    }

    #[Override]
    public function label(): string
    {
        return $this->trans('label');
    }

    #[Override]
    public function description(): string
    {
        return $this->trans('description');
    }

    #[Override]
    public function defaults(): array
    {
        $defaults = [
            'enabled' => false,
            'scan_json' => false,
            'scan_nova' => false,
            'log_blocked' => true,
            'block_threshold' => InputSanitizerSettings::DEFAULT_THRESHOLD,
            'view' => InputSanitizerSettings::DEFAULT_VIEW,
        ];

        foreach (Category::cases() as $category) {
            $defaults[$category->setting()] = $category->defaultPattern();
        }

        return $defaults + ['excluded_inputs' => [], 'excluded_headers' => []];
    }

    #[Override]
    public function rules(): array
    {
        $rules = [
            'enabled' => ['required', 'boolean'],
            'scan_json' => ['required', 'boolean'],
            'scan_nova' => ['required', 'boolean'],
            'log_blocked' => ['required', 'boolean'],
            'block_threshold' => ['required', 'integer', 'min:1', 'max:' . InputSanitizerSettings::MAX_THRESHOLD],
            'view' => ['bail', 'required', 'string', 'max:150', 'regex:' . InputSanitizerSettings::VIEW_NAME, new ExistingView()],
            'excluded_inputs' => ['present', 'array', 'max:150'],
            'excluded_inputs.*.name' => ['required', 'string', 'max:100', 'regex:' . InputSanitizerSettings::INPUT_NAME],
            'excluded_headers' => ['present', 'array', 'max:150'],
            'excluded_headers.*.name' => ['required', 'string', 'max:100', 'regex:' . InputSanitizerSettings::HEADER_NAME],
        ];

        foreach (Category::cases() as $category) {
            $rules[$category->setting()] = ['nullable', 'string', 'max:1000', new CompilablePattern()];
        }

        return $rules;
    }

    /**
     * @return list<Field>
     */
    #[Override]
    public function fields(): array
    {
        $fields = [
            Field::toggle('enabled', $this->trans('fields.enabled'), $this->trans('help.enabled')),
            Field::number('block_threshold', $this->trans('fields.block_threshold'), $this->trans('help.block_threshold')),
            Field::text('view', $this->trans('fields.view'), $this->trans('help.view'), InputSanitizerSettings::DEFAULT_VIEW),
            Field::toggle('scan_json', $this->trans('fields.scan_json'), $this->trans('help.scan_json')),
            Field::toggle('scan_nova', $this->trans('fields.scan_nova'), $this->trans('help.scan_nova')),
            Field::toggle('log_blocked', $this->trans('fields.log_blocked'), $this->trans('help.log_blocked')),
        ];

        foreach (Category::cases() as $category) {
            $fields[] = Field::textarea($category->setting(), $category->label(), $this->trans('help.patterns'));
        }

        $fields[] = Field::table('excluded_inputs', $this->trans('fields.excluded_inputs'), [Field::text('name', $this->trans('fields.name'), placeholder: 'content')], $this->trans('help.excluded_inputs'));
        $fields[] = Field::table('excluded_headers', $this->trans('fields.excluded_headers'), [Field::text('name', $this->trans('fields.name'), placeholder: 'X-Template')], $this->trans('help.excluded_headers'));

        return $fields;
    }

    #[Override]
    public function status(array $values): CheckResult
    {
        $settings = InputSanitizerSettings::fromArray($values);
        $label = $this->label();

        if (!$settings->enabled) {
            return CheckResult::warn(self::KEY, $label, $this->trans('status.off'));
        }

        if ($settings->patterns === []) {
            return CheckResult::warn(self::KEY, $label, $this->trans('status.no_patterns'));
        }

        return CheckResult::pass(self::KEY, $label, $this->trans('status.on', [
            'count' => count($settings->patterns),
            'threshold' => $settings->blockThreshold,
        ]));
    }

    /**
     * @param array<string, int|string> $replace
     */
    private function trans(string $key, array $replace = []): string
    {
        return Message::get('aegis-input-sanitizer::input-sanitizer.' . $key, $replace);
    }
}
