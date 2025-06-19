<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormComment extends Model
{
    protected $table = 'form_comments';

    protected $fillable = [
        'form_version_id',
        'parent_comment_id',
        'element_id',
        'commenter',
        'email',
        'text',
        'x',
        'y',
        'resolved',
    ];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'resolved' => 'boolean',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormComment::class, 'parent_comment_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FormComment::class, 'parent_comment_id');
    }

    public function element(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'element_id');
    }



    /**
     * Get all comments for a form version as a threaded array.
     */
    public static function getThreadedComments($formVersionId)
    {
        $comments = self::where('form_version_id', $formVersionId)
            ->orderBy('created_at')
            ->get();

        $grouped = $comments->groupBy('parent_comment_id');

        $buildThread = function ($parentId) use (&$buildThread, $grouped) {
            return ($grouped[$parentId] ?? collect())->map(function ($comment) use (&$buildThread) {
                $comment->thread = $buildThread($comment->id);
                return $comment;
            });
        };

        // Top-level comments (parent_comment_id == null)
        return $buildThread(null);
    }

    /**
     * Get the threaded context for this comment (all ancestors and descendants).
     */
    public function getThreadedContextAttribute()
    {
        // Collect ancestors
        $ancestors = collect();
        $current = $this;
        while (
            $current->relationLoaded('parent') &&
            $current->parent
        ) {
            $ancestors->prepend($current->parent);
            $current = $current->parent;
        }

        // Collect descendants (recursive)
        $descendants = collect();
        $collectDescendants = function ($comment, $level = 1) use (&$collectDescendants, &$descendants) {
            if (!$comment->relationLoaded('children')) {
                return;
            }
            foreach ($comment->children as $child) {
                $descendants->push(['comment' => $child, 'level' => $level]);
                $collectDescendants($child, $level + 1);
            }
        };
        $collectDescendants($this);

        // Format context: ancestors, self, descendants (HTML)
        $html = '';
        foreach ($ancestors as $c) {
            $html .= '<div style="margin-bottom: 4px; padding: 4px; border-left: 3px solid #ccc; background: #f9f9f9;">'
                . '<strong>' . e($c->commenter) . ':</strong> ' . nl2br(e($c->text)) . '</div>';
        }
        $html .= '<div style="margin-bottom: 4px; padding: 4px; border-left: 3px solid #007bff; background: #eaf4ff;">'
            . '<strong>' . e($this->commenter) . ':</strong> ' . nl2br(e($this->text)) . '</div>';
        foreach ($descendants as $desc) {
            $c = $desc['comment'];
            $level = $desc['level'];
            $html .= '<div style="margin-bottom: 4px; padding: 4px; margin-left: ' . (20 * $level) . 'px; border-left: 3px solid #28a745; background: #f6fff6;">'
                . '<strong>' . e($c->commenter) . ':</strong> ' . nl2br(e($c->text)) . '</div>';
        }
        return $html;
    }
}
