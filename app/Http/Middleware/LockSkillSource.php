<?php

namespace App\Http\Middleware;

use App\Models\Agents\Skill;
use App\Models\Agents\SkillSource;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/** Serialize repository mutations with syncs, including reference/script edits. */
class LockSkillSource
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || str_ends_with($request->path(), '/sync')) {
            return $next($request);
        }

        $source = $request->route('skillSource');
        $skill = $request->route('skill');
        $sourceId = $source instanceof SkillSource ? $source->id : ($skill instanceof Skill ? $skill->skill_source_id : null);
        if ($sourceId === null) {
            return $next($request);
        }

        $lock = Cache::lock('skill-source:'.$sourceId, 360);
        abort_unless($lock->get(), 409, 'This repository is syncing. Try again when it finishes.');

        try {
            if ($source instanceof SkillSource) {
                $source->refresh();
            }
            if ($skill instanceof Skill) {
                $skill->refresh();
                abort_if($skill->trashed(), 404);
            }

            foreach (['reference', 'script'] as $parameter) {
                $record = $request->route($parameter);
                if ($record instanceof Model) {
                    $record->refresh();
                }
            }

            return $next($request);
        } finally {
            $lock->release();
        }
    }
}
