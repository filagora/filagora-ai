<?php

declare(strict_types=1);

use Filagora\Ai\Services\GroundingPrompt;

it('numbers the excerpts and prefixes an item with its label', function () {
    $text = (new GroundingPrompt)->excerpts([
        ['title' => 'Install', 'excerpt' => 'Run it.', 'url' => 'https://example.test/a'],
        ['title' => 'file.php:1-9', 'excerpt' => 'code', 'url' => 'https://example.test/b', 'label' => 'CODE'],
    ]);

    expect($text)->toContain("[1] Install\nRun it.\nhttps://example.test/a")
        ->and($text)->toContain("[2] CODE file.php:1-9\ncode\nhttps://example.test/b");
});

it('adds each note once, ahead of the excerpts', function () {
    $item = ['title' => 'T', 'excerpt' => 'E', 'url' => 'https://example.test', 'note' => 'The version was assumed.'];

    $text = (new GroundingPrompt)->excerpts([$item, $item]);

    expect(substr_count($text, 'The version was assumed.'))->toBe(1)
        ->and(strpos($text, 'The version was assumed.'))->toBeLessThan(strpos($text, 'Relevant excerpts'));
});

it('joins the rules each owner adds, and has none to start with', function () {
    $prompt = new GroundingPrompt;
    expect($prompt->rules())->toBe('');

    $prompt->addRules("  First rule.\n")->addRules('Second rule.');

    expect($prompt->rules())->toBe("First rule.\n\nSecond rule.");
});
