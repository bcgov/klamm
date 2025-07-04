<?php

namespace App\Filament\Components;

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

class AutocompleteBadgeList
{
    /**
     * Filament Placeholder badge list for autocomplete options used in styles/scripts.
     *
     * @param callable $autocompleteOptionsFn Closure ($get, $livewire) => array
     * @param string $context 'style' or 'script' (for formatting)
     * @param string $label Label for the badge list
     * @param string|null $emptyText Text to show if no options
     * @return Placeholder
     */
    public static function make(callable $autocompleteOptionsFn, string $context = 'style', string $label = 'Form Elements')
    {
        $emptyText = '<em>No form elements available.</em>';
        return Placeholder::make('autocomplete_options_list_' . $context)
            ->label(new HtmlString(
                $label . ' '
                    . '<span style="display:inline-block;vertical-align:middle;position:relative;" tabindex="0" id="klamm-badge-info-icon-' . $context . '">' .
                    '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 20 20" style="display:inline;vertical-align:middle;color:#2563eb;">'
                    . '<circle cx="10" cy="10" r="9" fill="#eff6ff" stroke="#2563eb" stroke-width="2"/>'
                    . '<text x="10" y="15" text-anchor="middle" font-size="12" fill="#2563eb" font-family="Arial, sans-serif">i</text>'
                    . '</svg>'
                    . '<span id="klamm-badge-tooltip-' . $context
                    . '" class="klamm-badge-tooltip" style="display:none;position:absolute;z-index:10;left:110%;top:50%;transform:translateY(-50%);background:#fff;border:1px solid #cbd5e1;padding:4px 8px;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,0.08);font-size:12px;white-space:nowrap;min-width:140px;">
                    Click on form element to copy ID to clipboard
                    </span>'
                    . '</span>'
            ))
            ->content(function ($get, $livewire) use ($autocompleteOptionsFn, $context, $emptyText) {
                $options = $autocompleteOptionsFn($get, $livewire);
                if (empty($options)) {
                    return new HtmlString($emptyText);
                }
                $script = "<script>(function(){\nfunction attachKlammBadgeHandlers(){\n  var container = document.getElementById('klamm-autocomplete-badge-list-" . $context . "');\n  if(container && !container.__klammHandlerAttached){\n    container.addEventListener('click', function(e){\n      if(e.target.classList.contains('klamm-badge')){\n        window.__klammBadgeCopyHandler(e);\n      }\n    });\n    container.addEventListener('keydown', function(e){\n      if(e.target.classList.contains('klamm-badge') && (e.key === 'Enter' || e.key === ' ')) {\n        window.__klammBadgeCopyHandler(e);\n        e.preventDefault();\n      }\n    });\n    container.__klammHandlerAttached = true;\n  }\n  // Tooltip logic\n  var infoIcon = document.getElementById('klamm-badge-info-icon-" . $context . "');\n  var tooltip = document.getElementById('klamm-badge-tooltip-" . $context . "');\n  if(infoIcon && tooltip && !infoIcon.__klammTooltipAttached){\n    var show = function(){ tooltip.style.display = 'block'; }\n    var hide = function(){ tooltip.style.display = 'none'; }\n    infoIcon.addEventListener('mouseenter', show);\n    infoIcon.addEventListener('mouseleave', hide);\n    infoIcon.addEventListener('focus', show);\n    infoIcon.addEventListener('blur', hide);\n    infoIcon.addEventListener('click', function(e){\n      tooltip.style.display = (tooltip.style.display === 'block') ? 'none' : 'block';\n      e.stopPropagation();\n    });\n    document.addEventListener('click', function(e){\n      if(!infoIcon.contains(e.target)){ tooltip.style.display = 'none'; }\n    });\n    infoIcon.__klammTooltipAttached = true;\n  }\n}\nwindow.__klammBadgeCopyHandler = window.__klammBadgeCopyHandler || function(e){\n  var uuid = e.target.getAttribute('data-uuid');\n  if(uuid){\n    navigator.clipboard.writeText(uuid);\n    e.target.classList.add('ring-2','ring-blue-400');\n    setTimeout(function(){e.target.classList.remove('ring-2','ring-blue-400');}, 600);\n  }\n};\n// Always re-attach on DOMContentLoaded and after a short delay (for Livewire/Filament tab switches)\ndocument.addEventListener('DOMContentLoaded', attachKlammBadgeHandlers);\nsetTimeout(attachKlammBadgeHandlers, 100);\nsetTimeout(attachKlammBadgeHandlers, 500);\nsetTimeout(attachKlammBadgeHandlers, 1000);\n})();</script>";
                $badges = collect($options)
                    ->map(function ($opt) {
                        $label = e($opt['label'] ?? '');
                        $documentation = e($opt['documentation'] ?? '');
                        $insertText = $opt['insertText'] ?? '';
                        $copyText = $insertText;
                        $tooltip = "$documentation";
                        return "<span class='klamm-badge inline-block text-xs font-semibold mb-2 px-2.5 py-0.5 rounded' style='cursor:pointer;' title='" . htmlspecialchars($tooltip, ENT_QUOTES) . "' data-uuid='" . htmlspecialchars($copyText, ENT_QUOTES) . "' tabindex='0' role='button' aria-label='Copy selector for " . htmlspecialchars($label, ENT_QUOTES) . "'>" . $label . "</span>";
                    })
                    ->implode('');
                $scrollBox = "<style>\n"
                    . ".klamm-autocomplete-badge-list-{$context} {background-color:#f8fafc;border-radius:0.5rem;max-height:600px;overflow-y:auto;}\n"
                    . ".dark .klamm-autocomplete-badge-list-{$context} {background-color:#1e293b;}\n"
                    . ".klamm-badge-tooltip {background:#fff;color:#1e293b;border:1px solid #cbd5e1;}\n"
                    . ".dark .klamm-badge-tooltip {background:#334155;color:#f1f5f9;border:1px solid #64748b;}\n"
                    . ".klamm-badge {background-color:#2563eb;color:#fff;transition:background 0.15s, color 0.15s;}\n"
                    . ".klamm-badge:focus, .klamm-badge:hover {background-color:#1d4ed8;color:#fff;}\n"
                    . ".dark .klamm-badge {background-color:#3b82f6;color:#fff;box-shadow:0 1px 4px 0 rgba(59,130,246,0.15);}\n"
                    . ".dark .klamm-badge:focus, .dark .klamm-badge:hover {background-color:#2563eb;color:#fff;}\n"
                    . "</style>\n"
                    . "<div><div id='klamm-autocomplete-badge-list-{$context}' class='klamm-autocomplete-badge-list-{$context}' style='display:flex;flex-wrap:wrap;align-content:flex-start;gap:0.25rem;padding:0.5rem 0.25rem;'>$badges</div></div>";
                return new HtmlString($scrollBox . $script);
            })
            ->columnSpan(1);
    }
}
