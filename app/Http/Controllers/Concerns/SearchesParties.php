<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The party picker, shared by every counter form that attaches a customer or a
 * supplier. Both are the same shape — a name, a company, two phone numbers and
 * an active flag — so they are searched the same way.
 *
 * Answers a *search*, not "give me the whole book": shipping every active party
 * to the browser is both slow once a shop has a few thousand of them and
 * needlessly wide — the picker only ever needs enough to tell two of them apart
 * at the counter.
 *
 * Callers differ only in what "recent" means to them (last device brought in,
 * last invoice issued), so that ordering is passed in.
 */
trait SearchesParties
{
    /**
     * @param  class-string<Model>  $model
     * @param  class-string<JsonResource>  $resource
     * @param  Closure(Builder): Builder|null  $recency  Ordering applied when nothing has been
     *                                                   typed yet; null falls back to alphabetical.
     */
    protected function partyOptionsResponse(
        Request $request,
        string $model,
        string $resource,
        ?Closure $recency = null,
    ): JsonResponse {
        $search = trim((string) $request->string('search'));
        $limit = max(1, min($request->integer('limit', 20), 50));

        $matches = $model::query()
            ->where('is_active', true)
            ->when($search !== '', fn (Builder $q) => $this->applyPartySearch($q, $search))
            ->when(
                $search === '' && $recency !== null,
                fn (Builder $q) => $recency($q),
                fn (Builder $q) => $q->orderBy('name'),
            )
            ->limit($limit)
            ->get();

        // A document being edited may point at a party who has since been
        // deactivated or who falls outside this slice. Without them the picker
        // would render blank over a perfectly valid id.
        $include = $request->integer('include');
        if ($include > 0 && ! $matches->contains('id', $include)) {
            $pinned = $model::query()->find($include);
            if ($pinned) {
                $matches->prepend($pinned);
            }
        }

        $total = $model::query()->where('is_active', true)->count();

        return response()->json([
            'data' => $resource::collection($matches),
            'meta' => [
                'total' => $total,
                'returned' => $matches->count(),
                // Tells the UI to say "keep typing" instead of implying the
                // list it just rendered is the whole book.
                'truncated' => $matches->count() >= $limit,
            ],
        ]);
    }

    protected function applyPartySearch(Builder $query, string $search): Builder
    {
        $like = '%'.$search.'%';
        // Phones get typed with spaces, dashes and Arabic-Indic digits; compare
        // on the bare digits so "0999 123 456" still finds "0999123456".
        $digits = preg_replace('/\D+/', '', $this->toLatinDigits($search));

        return $query->where(function (Builder $q) use ($like, $digits) {
            $q->where('name', 'like', $like)
                ->orWhere('company', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('mobile', 'like', $like);

            if ($digits !== '') {
                $q->orWhere('phone', 'like', "%{$digits}%")
                    ->orWhere('mobile', 'like', "%{$digits}%");
            }
        });
    }

    protected function toLatinDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
