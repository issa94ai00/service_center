<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TechnicianStoreRequest;
use App\Http\Requests\TechnicianUpdateRequest;
use App\Http\Resources\TechnicianResource;
use App\Models\Technician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TechniciansController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Technician::class);

        $technicians = Technician::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where(function (Builder $q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('specialty', 'like', "%{$request->search}%")
                        ->orWhere('phone', 'like', "%{$request->search}%")
                        ->orWhere('mobile', 'like', "%{$request->search}%");
                });
            })
            ->when($request->filled('is_active'), function (Builder $query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->withAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return TechnicianResource::collection($technicians);
    }

    /**
     * قائمة مختصرة لقوائم الاختيار في شاشة البلاغ، تحمل النسبة الافتراضية حتى
     * تعرض الشاشة نصيب الخبير قبل الحفظ.
     */
    public function options(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Technician::class);

        $technicians = Technician::query()
            ->where('is_active', true)
            ->when($request->filled('search'), fn (Builder $q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'specialty', 'commission_rate']);

        return response()->json(['data' => $technicians]);
    }

    public function store(TechnicianStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Technician::class);

        /** @var Technician $technician */
        $technician = Technician::query()->create($request->validated());

        return response()->json([
            'message' => __('تم إنشاء خبير الصيانة بنجاح.'),
            'data' => new TechnicianResource($technician),
        ], 201);
    }

    public function show(Technician $technician): JsonResponse
    {
        $this->authorize('view', $technician);

        $technician->loadAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'));

        return response()->json([
            'data' => new TechnicianResource($technician),
        ]);
    }

    public function update(TechnicianUpdateRequest $request, Technician $technician): JsonResponse
    {
        $this->authorize('update', $technician);

        $technician->update($request->validated());
        $technician->loadAggregate('transactions as tx_net', DB::raw('sum(debit - credit)'));

        return response()->json([
            'message' => __('تم تعديل خبير الصيانة بنجاح.'),
            'data' => new TechnicianResource($technician),
        ]);
    }

    public function destroy(Technician $technician): JsonResponse
    {
        $this->authorize('delete', $technician);

        // خبير له تاريخ محاسبي لا يُحذف: حذفه يترك بلاغات مسوّاة بلا صاحب
        // وقيوداً في دفتر لا يمكن فتحه. التعطيل هو البديل.
        $referenced = DB::table('repairs')->where('technician_id', $technician->id)->exists()
            || DB::table('technician_transactions')->where('technician_id', $technician->id)->exists()
            || DB::table('payments')->where('technician_id', $technician->id)->exists();

        if ($referenced) {
            return response()->json([
                'message' => __('لهذا الخبير سجل من البلاغات أو الحركات المالية، ولا يمكن حذفه. يمكن تعطيله بدلاً من ذلك.'),
            ], 422);
        }

        $technician->delete();

        return response()->json([
            'message' => __('تم حذف خبير الصيانة بنجاح.'),
        ]);
    }
}
