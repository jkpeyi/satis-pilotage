<?php

namespace App\Livewire\Forms;

use App\Models\TestRun;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Form;

class TestRunForm extends Form
{
    public ?TestRun $testRun = null;

    public string $tool_name = '';

    public string $tool_version = '';

    public string $executed_on = '';

    public string $tests_total = '';

    public string $tests_passed = '';

    public string $tests_failed = '';

    public string $tests_skipped = '';

    public string $line_coverage = '';

    public string $scope = '';

    /** @var TemporaryUploadedFile|null JUnit XML report, read to fill the counts */
    public $junit_file = null;

    /** @var TemporaryUploadedFile|null Clover or Cobertura report, read to fill the coverage */
    public $coverage_file = null;

    public string $source_file_name = '';

    public string $source_sha256 = '';

    public function setTestRun(TestRun $testRun): void
    {
        $this->resetValidation();
        $this->testRun = $testRun;
        $this->tool_name = $testRun->tool_name;
        $this->tool_version = (string) $testRun->tool_version;
        $this->executed_on = $testRun->executed_on->toDateString();
        $this->tests_total = (string) $testRun->tests_total;
        $this->tests_passed = (string) $testRun->tests_passed;
        $this->tests_failed = (string) $testRun->tests_failed;
        $this->tests_skipped = (string) $testRun->tests_skipped;
        $this->line_coverage = $testRun->line_coverage !== null ? (string) (float) $testRun->line_coverage : '';
        $this->scope = (string) $testRun->scope;
        $this->source_file_name = (string) $testRun->source_file_name;
        $this->source_sha256 = (string) $testRun->source_sha256;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'tool_name' => ['required', 'string', 'max:100'],
            'tool_version' => ['nullable', 'string', 'max:64'],
            'executed_on' => ['required', 'date', 'before_or_equal:today'],
            'tests_total' => ['nullable', 'integer', 'min:0'],
            'tests_passed' => ['nullable', 'integer', 'min:0'],
            'tests_failed' => ['nullable', 'integer', 'min:0'],
            'tests_skipped' => ['nullable', 'integer', 'min:0'],
            'line_coverage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scope' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'tool_name' => 'outil', 'executed_on' => 'date d’exécution', 'tests_total' => 'nombre de tests',
            'tests_passed' => 'réussis', 'tests_failed' => 'en échec', 'tests_skipped' => 'ignorés', 'line_coverage' => 'couverture',
        ];
    }

    /**
     * @return array{tool_name: string, tool_version: ?string, executed_on: string, tests_total: ?int, tests_passed: ?int, tests_failed: ?int, tests_skipped: ?int, line_coverage: ?string, scope: ?string, source_file_name: ?string, source_sha256: ?string}
     */
    public function data(): array
    {
        $this->line_coverage = str_replace(',', '.', trim($this->line_coverage));
        $this->validate();
        $nullable = fn (string $value): ?string => trim($value) !== '' ? trim($value) : null;
        $count = fn (string $value): ?int => trim($value) !== '' ? (int) $value : null;

        return [
            'tool_name' => trim($this->tool_name),
            'tool_version' => $nullable($this->tool_version),
            'executed_on' => $this->executed_on,
            'tests_total' => $count($this->tests_total),
            'tests_passed' => $count($this->tests_passed),
            'tests_failed' => $count($this->tests_failed),
            'tests_skipped' => $count($this->tests_skipped),
            'line_coverage' => $nullable($this->line_coverage),
            'scope' => $nullable($this->scope),
            'source_file_name' => $nullable($this->source_file_name),
            'source_sha256' => $nullable($this->source_sha256),
        ];
    }
}
