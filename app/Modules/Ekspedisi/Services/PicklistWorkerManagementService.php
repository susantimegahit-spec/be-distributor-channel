<?php

namespace App\Modules\Ekspedisi\Services;

use App\Models\Item;
use App\Models\LoadingRate;
use App\Models\LoadingRateType;
use App\Models\Picklist;
use App\Models\PicklistItem;
use App\Models\PicklistWorker;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class PicklistWorkerManagementService
{
    /**
     * Get paginated list of picklists that have been submitted by checker or add-do,
     * along with worker calculation summaries (regular vs overtime).
     *
     * @param array $filters
     * @return array
     */
    public function getWorkerManagementPicklists(array $filters = []): array
    {
        $query = Picklist::with([
            'items.salesOrder:id,order_no,sap_doc_num,customer_name,card_code,distributor_id',
            'items.salesOrder.distributor:id,name,code_customer,depo',
            'workers',
            'creator:id,name,username',
        ]);

        // Only picklists that are submitted by checker or add-do:
        // Status weigh_out, complete, or has delivery_order_no, or has workers, or all items checked
        $query->where(function (Builder $q) {
            $q->whereIn('status', [Picklist::STATUS_WEIGH_OUT, Picklist::STATUS_COMPLETE, 'weigh_out', 'complete'])
                ->orWhereNotNull('delivery_order_no')
                ->orWhereNotNull('do_doc_entry')
                ->orWhereHas('workers')
                ->orWhere(function (Builder $sq) {
                    $sq->whereDoesntHave('items', fn($iq) => $iq->where('is_checked', false))
                        ->whereHas('items');
                });
        });

        // Filter by shipping type
        if (!empty($filters['shipping_type'])) {
            $query->where('shipping_type', strtolower(trim((string) $filters['shipping_type'])));
        }

        // Filter by status (optional)
        if (!empty($filters['status'])) {
            $st = trim((string) $filters['status']);
            $query->where(function (Builder $q) use ($st) {
                $q->where('status', strtolower($st))
                    ->orWhere('status', strtoupper($st));
            });
        }

        // Filter by date range (posting_date)
        if (!empty($filters['date_from'])) {
            $query->whereDate('posting_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('posting_date', '<=', $filters['date_to']);
        }

        // Search filter
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $isPgsql = (new Picklist())->getConnection()->getDriverName() === 'pgsql';
            $likeOp = $isPgsql ? 'ilike' : 'like';

            $matchingSoIds = \App\Models\SalesOrder::where('order_no', $likeOp, "%{$search}%")
                ->orWhere('sap_doc_num', $likeOp, "%{$search}%")
                ->orWhere('customer_name', $likeOp, "%{$search}%")
                ->pluck('id')
                ->toArray();

            $query->where(function (Builder $q) use ($search, $likeOp, $matchingSoIds) {
                $q->where('picklist_no', $likeOp, "%{$search}%")
                    ->orWhere('delivery_order_no', $likeOp, "%{$search}%")
                    ->orWhere('license_plate', $likeOp, "%{$search}%")
                    ->orWhere('driver_name', $likeOp, "%{$search}%")
                    ->orWhere('checker_name', $likeOp, "%{$search}%")
                    ->orWhere('comments', $likeOp, "%{$search}%")
                    ->orWhereHas('workers', function (Builder $wq) use ($search, $likeOp) {
                        $wq->where('worker_name', $likeOp, "%{$search}%")
                            ->orWhere('item_code', $likeOp, "%{$search}%")
                            ->orWhere('item_name', $likeOp, "%{$search}%");
                    });

                if (!empty($matchingSoIds)) {
                    $q->orWhereHas('items', function (Builder $iq) use ($matchingSoIds) {
                        $iq->whereIn('sales_order_id', $matchingSoIds);
                    });
                }
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min(100, $perPage));

        $paginator = $query->orderBy('id', 'desc')->paginate($perPage);

        // Preload active loading rates for items involved to calculate fast in bulk
        $allItemCodes = [];
        foreach ($paginator->items() as $p) {
            foreach ($p->workers as $w) {
                if (!empty($w->item_code)) {
                    $allItemCodes[] = $w->item_code;
                }
            }
            foreach ($p->items as $it) {
                if (!empty($it->item_code)) {
                    $allItemCodes[] = $it->item_code;
                }
            }
        }
        $allItemCodes = array_unique($allItemCodes);

        $masterRates = empty($allItemCodes)
            ? collect()
            : LoadingRate::whereIn('item_code', $allItemCodes)
                ->where('status', LoadingRate::STATUS_ACTIVE)
                ->get();

        $rateTypes = LoadingRateType::where('is_active', true)->get();

        $filterRateType = !empty($filters['rate_type']) ? strtoupper(trim((string) $filters['rate_type'])) : null;

        $formattedData = [];
        foreach ($paginator->items() as $picklist) {
            $analysis = $this->calculatePicklistWorkerSummary($picklist, $masterRates, $rateTypes);

            // Optional filter by determined rate_type
            if ($filterRateType && $analysis['summary']['determined_rate_type'] !== $filterRateType) {
                continue;
            }

            // Extract unique sales orders
            $salesOrders = [];
            $seenSoIds = [];
            foreach ($picklist->items as $item) {
                $so = $item->salesOrder;
                if ($so && !isset($seenSoIds[$so->id])) {
                    $seenSoIds[$so->id] = true;
                    $salesOrders[] = [
                        'id'               => $so->id,
                        'order_no'         => (string) ($so->sap_doc_num ?: $so->order_no),
                        'sap_doc_num'      => $so->sap_doc_num,
                        'customer_name'    => $so->customer_name,
                        'distributor_name' => $so->distributor?->name,
                    ];
                }
            }

            $formattedData[] = [
                'id'                  => $picklist->id,
                'picklist_no'         => $picklist->picklist_no,
                'shipping_type'       => $picklist->shipping_type,
                'status'              => $picklist->status,
                'posting_date'        => $picklist->posting_date ? Carbon::parse($picklist->posting_date)->format('Y-m-d') : null,
                'due_date'            => $picklist->due_date ? Carbon::parse($picklist->due_date)->format('Y-m-d') : null,
                'delivery_order_no'   => $picklist->delivery_order_no,
                'do_doc_num'          => $picklist->do_doc_num,
                'do_doc_entry'        => $picklist->do_doc_entry,
                'license_plate'       => $picklist->license_plate,
                'driver_name'         => $picklist->driver_name,
                'checker_name'        => $picklist->checker_name,
                'comments'            => $picklist->comments,
                'seal_number'         => $picklist->seal_number,
                'total_weight'        => (float) $picklist->total_weight,
                'weigh_in_weight'     => (float) $picklist->weigh_in_weight,
                'weigh_in_at'         => $picklist->weigh_in_at ? Carbon::parse($picklist->weigh_in_at)->toIso8601String() : null,
                'weigh_out_weight'    => (float) $picklist->weigh_out_weight,
                'weigh_out_at'        => $picklist->weigh_out_at ? Carbon::parse($picklist->weigh_out_at)->toIso8601String() : null,
                'start_time'          => $analysis['summary']['start_time'],
                'end_time'            => $analysis['summary']['end_time'],
                'calculation_summary' => $analysis['summary'],
                'workers'             => $analysis['workers'],
                'sales_orders'        => $salesOrders,
                'created_at'          => $picklist->created_at ? Carbon::parse($picklist->created_at)->toIso8601String() : null,
                'updated_at'          => $picklist->updated_at ? Carbon::parse($picklist->updated_at)->toIso8601String() : null,
            ];
        }

        return [
            'data' => $formattedData,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    /**
     * Get detailed worker management analysis for a single picklist.
     *
     * @param int $id
     * @return array
     * @throws \Exception
     */
    public function getWorkerManagementDetail(int $id): array
    {
        $picklist = Picklist::with([
            'items.salesOrder.distributor',
            'items.salesOrderDetail',
            'items.item',
            'items.labelPhotos',
            'signatures.creator:id,name',
            'workers',
            'creator:id,name,username',
            'updater:id,name,username',
        ])->find($id);

        if (!$picklist) {
            throw new \Exception("Picklist with ID #{$id} not found.", 404);
        }

        $allItemCodes = $picklist->workers->pluck('item_code')
            ->merge($picklist->items->pluck('item_code'))
            ->unique()
            ->filter()
            ->values();

        $masterRates = LoadingRate::whereIn('item_code', $allItemCodes)
            ->where('status', LoadingRate::STATUS_ACTIVE)
            ->get();

        $rateTypes = LoadingRateType::where('is_active', true)->get();

        $analysis = $this->calculatePicklistWorkerSummary($picklist, $masterRates, $rateTypes, detailed: true);

        // Clean items structure with Uom rule adherence
        $items = [];
        foreach ($picklist->items as $pItem) {
            $items[] = [
                'id'                    => $pItem->id,
                'sales_order_id'        => $pItem->sales_order_id,
                'sales_order_detail_id' => $pItem->sales_order_detail_id,
                'order_no'              => $pItem->salesOrder ? (string) ($pItem->salesOrder->sap_doc_num ?: $pItem->salesOrder->order_no) : null,
                'customer_name'         => $pItem->salesOrder?->customer_name,
                'item_code'             => (string) $pItem->item_code,
                'item_name'             => $pItem->item_name,
                'whs_code'              => $pItem->whs_code,
                'uom'                   => $pItem->unit_msr ?: ($pItem->item?->sal_unit_msr ?? 'SAK'),
                'ordered_qty'           => (float) $pItem->ordered_qty,
                'pick_qty'              => (float) $pItem->pick_qty,
                'checked_qty'           => (float) $pItem->checked_qty,
                'is_checked'            => (bool) $pItem->is_checked,
                'checked_by_checker'    => $pItem->checked_by_checker,
                'checked_at'            => $pItem->checked_at ? Carbon::parse($pItem->checked_at)->toIso8601String() : null,
                'start_time'            => $pItem->start_time ? Carbon::parse($pItem->start_time)->toIso8601String() : null,
                'end_time'              => $pItem->end_time ? Carbon::parse($pItem->end_time)->toIso8601String() : null,
                'production_date'       => $pItem->production_date ? Carbon::parse($pItem->production_date)->format('Y-m-d') : null,
            ];
        }

        // Clean signatures
        $signatures = [];
        foreach ($picklist->signatures as $sig) {
            $signatures[] = [
                'id'          => $sig->id,
                'signer_type' => $sig->signer_type,
                'signer_name' => $sig->signer_name,
                'notes'       => $sig->notes,
                'signed_at'   => $sig->signed_at ? Carbon::parse($sig->signed_at)->toIso8601String() : null,
            ];
        }

        return [
            'picklist' => [
                'id'                  => $picklist->id,
                'picklist_no'         => $picklist->picklist_no,
                'shipping_type'       => $picklist->shipping_type,
                'status'              => $picklist->status,
                'posting_date'        => $picklist->posting_date ? Carbon::parse($picklist->posting_date)->format('Y-m-d') : null,
                'due_date'            => $picklist->due_date ? Carbon::parse($picklist->due_date)->format('Y-m-d') : null,
                'delivery_order_no'   => $picklist->delivery_order_no,
                'do_doc_num'          => $picklist->do_doc_num,
                'do_doc_entry'        => $picklist->do_doc_entry,
                'do_status'           => $picklist->do_status,
                'license_plate'       => $picklist->license_plate,
                'driver_name'         => $picklist->driver_name,
                'checker_name'        => $picklist->checker_name,
                'comments'            => $picklist->comments,
                'seal_number'         => $picklist->seal_number,
                'total_weight'        => (float) $picklist->total_weight,
                'weigh_in_weight'     => (float) $picklist->weigh_in_weight,
                'weigh_in_at'         => $picklist->weigh_in_at ? Carbon::parse($picklist->weigh_in_at)->toIso8601String() : null,
                'weigh_out_weight'    => (float) $picklist->weigh_out_weight,
                'weigh_out_at'        => $picklist->weigh_out_at ? Carbon::parse($picklist->weigh_out_at)->toIso8601String() : null,
                'start_time'          => $analysis['summary']['start_time'],
                'end_time'            => $analysis['summary']['end_time'],
                'created_by'          => $picklist->creator?->name,
                'created_at'          => $picklist->created_at ? Carbon::parse($picklist->created_at)->toIso8601String() : null,
                'updated_at'          => $picklist->updated_at ? Carbon::parse($picklist->updated_at)->toIso8601String() : null,
            ],
            'calculation_summary'  => $analysis['summary'],
            'workers'              => $analysis['workers'],
            'breakdown_by_worker'  => $analysis['breakdown_by_worker'],
            'breakdown_by_item'    => $analysis['breakdown_by_item'],
            'items'                => $items,
            'signatures'           => $signatures,
        ];
    }

    /**
     * Determine whether a picklist operation is classified as REGULAR or OVERTIME (Lembur).
     *
     * @param Picklist $picklist
     * @param \Illuminate\Support\Collection|null $rateTypes
     * @return array
     */
    public function determineRateType(Picklist $picklist, $rateTypes = null): array
    {
        $rateTypes = $rateTypes ?: LoadingRateType::where('is_active', true)->get();

        $refDateTime = null;
        $sourceDesc = 'waktu pembaruan picklist';

        $startTimes = [];
        $endTimes = [];

        if (!empty($picklist->start_time)) {
            $startTimes[] = Carbon::parse($picklist->start_time);
        }
        if (!empty($picklist->end_time)) {
            $endTimes[] = Carbon::parse($picklist->end_time);
        }

        // Priority 1: Picklist items' start_time, end_time, or checked_at
        $latestItemTime = null;
        foreach ($picklist->items as $item) {
            if (!empty($item->start_time)) {
                $pStart = Carbon::parse($item->start_time);
                $startTimes[] = $pStart;
                if (!$latestItemTime || $pStart->gt($latestItemTime)) {
                    $latestItemTime = $pStart;
                    $sourceDesc = 'jam mulai muat item (start_time)';
                }
            }
            if (!empty($item->end_time)) {
                $pEnd = Carbon::parse($item->end_time);
                $endTimes[] = $pEnd;
                if (!$latestItemTime || $pEnd->gt($latestItemTime)) {
                    $latestItemTime = $pEnd;
                    $sourceDesc = 'jam selesai pemeriksaan item (end_time)';
                }
            } elseif (!empty($item->checked_at)) {
                $pChecked = Carbon::parse($item->checked_at);
                $endTimes[] = $pChecked;
                if (!$latestItemTime || $pChecked->gt($latestItemTime)) {
                    $latestItemTime = $pChecked;
                    $sourceDesc = 'jam submit checker (checked_at)';
                }
            }
        }

        if ($latestItemTime) {
            $refDateTime = $latestItemTime;
        } elseif (!empty($picklist->weigh_out_at)) {
            $refDateTime = Carbon::parse($picklist->weigh_out_at);
            $sourceDesc = 'jam timbang keluar (weigh_out_at)';
        } elseif (!empty($picklist->weigh_in_at)) {
            $refDateTime = Carbon::parse($picklist->weigh_in_at);
            $sourceDesc = 'jam timbang masuk (weigh_in_at)';
        } elseif ($picklist->workers->isNotEmpty()) {
            $firstWorker = $picklist->workers->first();
            if (!empty($firstWorker->created_at)) {
                $refDateTime = Carbon::parse($firstWorker->created_at);
                $sourceDesc = 'jam input data tenaga kerja';
            }
        }

        if (!$refDateTime) {
            $refDateTime = $picklist->updated_at ? Carbon::parse($picklist->updated_at) : now();
        }

        $earliestStart = !empty($startTimes)
            ? collect($startTimes)->min()
            : (!empty($picklist->weigh_in_at) ? Carbon::parse($picklist->weigh_in_at) : null);

        $latestEnd = !empty($endTimes)
            ? collect($endTimes)->max()
            : (!empty($picklist->weigh_out_at) ? Carbon::parse($picklist->weigh_out_at) : null);

        $timeStr = $refDateTime->format('H:i:s');
        $timeEvaluation = $this->determineTimeRateType($timeStr, $rateTypes);

        return [
            'rate_type'           => $timeEvaluation['rate_type'],
            'is_overtime'         => $timeEvaluation['is_overtime'],
            'start_time'          => $earliestStart ? $earliestStart->toIso8601String() : null,
            'end_time'            => $latestEnd ? $latestEnd->toIso8601String() : null,
            'reference_time'      => $timeStr,
            'reference_timestamp' => $refDateTime->toIso8601String(),
            'source_description'  => $sourceDesc,
            'reason'              => "Waktu operasional {$timeStr} ({$sourceDesc}) " . ($timeEvaluation['is_overtime'] ? "masuk jam lembur." : "masuk jam kerja reguler."),
        ];
    }

    /**
     * Determine rate type (REGULAR or OVERTIME) for a specific time string (H:i:s).
     *
     * @param string|null $timeStr
     * @param \Illuminate\Support\Collection|null $rateTypes
     * @return array
     */
    public function determineTimeRateType(?string $timeStr, $rateTypes = null): array
    {
        $rateTypes = $rateTypes ?: LoadingRateType::where('is_active', true)->get();

        $otType = $rateTypes->first(fn($t) => strtoupper($t->code) === LoadingRateType::TYPE_OVERTIME || strtoupper($t->code) === 'LEMBUR');
        $regType = $rateTypes->first(fn($t) => strtoupper($t->code) === LoadingRateType::TYPE_REGULAR || strtoupper($t->code) === 'REGULER');

        $timeStr = $timeStr ? substr($timeStr, 0, 8) : now()->format('H:i:s');

        if ($otType && $otType->isTimeInRange($timeStr)) {
            return [
                'rate_type'   => LoadingRateType::TYPE_OVERTIME,
                'is_overtime' => true,
                'reason'      => "Waktu {$timeStr} berada dalam jam lembur ({$otType->start_time} - {$otType->end_time}).",
            ];
        }

        if ($regType && $regType->isTimeInRange($timeStr)) {
            return [
                'rate_type'   => LoadingRateType::TYPE_REGULAR,
                'is_overtime' => false,
                'reason'      => "Waktu {$timeStr} berada dalam jam kerja reguler ({$regType->start_time} - {$regType->end_time}).",
            ];
        }

        // Standard fallback comparison: 16:30 - 08:00 is overtime
        if ($timeStr >= '16:30:00' || $timeStr < '08:00:00') {
            return [
                'rate_type'   => LoadingRateType::TYPE_OVERTIME,
                'is_overtime' => true,
                'reason'      => "Waktu {$timeStr} berada di luar jam kerja reguler (masuk lembur).",
            ];
        }

        return [
            'rate_type'   => LoadingRateType::TYPE_REGULAR,
            'is_overtime' => false,
            'reason'      => "Waktu {$timeStr} berada dalam jam kerja reguler.",
        ];
    }

    /**
     * Calculate worker calculation breakdown and comparison between regular and overtime.
     * When worker records are empty, automatically calculates loading fees from picklist items and their start_time.
     *
     * @param Picklist $picklist
     * @param \Illuminate\Support\Collection $masterRates
     * @param \Illuminate\Support\Collection $rateTypes
     * @param bool $detailed
     * @return array
     */
    public function calculatePicklistWorkerSummary(
        Picklist $picklist,
        $masterRates,
        $rateTypes,
        bool $detailed = false
    ): array {
        $determination = $this->determineRateType($picklist, $rateTypes);
        $determinedType = $determination['rate_type'];
        $isOvertime = $determination['is_overtime'];

        $workers = $picklist->workers;
        $formattedWorkers = [];
        $totalQty = 0.0;
        $totalRegularAmount = 0.0;
        $totalOvertimeAmount = 0.0;
        $totalFee = 0.0;
        $workerNames = [];

        $byWorkerGroup = [];
        $byItemGroup = [];

        $hasExplicitWorkers = $workers->isNotEmpty();

        if ($hasExplicitWorkers) {
            // Case A: Explicit worker records saved on picklist
            foreach ($workers as $w) {
                $itemCode = (string) $w->item_code;
                $qty = (float) $w->qty;
                $totalQty += $qty;
                $wName = !empty($w->worker_name) ? trim((string) $w->worker_name) : 'Tanpa Nama';
                $workerNames[$wName] = true;

                // Lookup regular master rate
                $regModel = $masterRates->first(function ($r) use ($itemCode) {
                    return $r->item_code === $itemCode &&
                        (strtoupper($r->rate_type) === LoadingRateType::TYPE_REGULAR || strtoupper($r->rate_type) === 'REGULER');
                });
                $regularRate = $regModel ? (float) $regModel->rate : 0.0;

                // Lookup overtime master rate
                $otModel = $masterRates->first(function ($r) use ($itemCode) {
                    return $r->item_code === $itemCode &&
                        (strtoupper($r->rate_type) === LoadingRateType::TYPE_OVERTIME || strtoupper($r->rate_type) === 'LEMBUR');
                });
                $overtimeRate = $otModel ? (float) $otModel->rate : 0.0;

                // Calculate fees for both tiers
                $regularFee = round($qty * $regularRate, 2);
                $lemburFee = round($qty * $overtimeRate, 2);

                $totalRegularAmount += $regularFee;
                $totalOvertimeAmount += $lemburFee;

                // Determine active applied rate and type
                $savedRate = (float) $w->rate;
                $savedRateType = !empty($w->rate_type) ? strtoupper((string) $w->rate_type) : null;

                if ($savedRate > 0) {
                    $appliedRate = $savedRate;
                    $appliedType = $savedRateType ?: $determinedType;
                    $activeFee = (float) $w->lembur_fee > 0 ? (float) $w->lembur_fee : round($qty * $appliedRate, 2);
                } else {
                    if ($isOvertime) {
                        $appliedRate = $overtimeRate > 0 ? $overtimeRate : $regularRate;
                        $appliedType = LoadingRateType::TYPE_OVERTIME;
                        $activeFee = $lemburFee;
                    } else {
                        $appliedRate = $regularRate > 0 ? $regularRate : $overtimeRate;
                        $appliedType = LoadingRateType::TYPE_REGULAR;
                        $activeFee = $regularFee;
                    }
                }

                $totalFee += $activeFee;

                $workerRow = [
                    'id'                     => $w->id,
                    'item_code'              => $itemCode,
                    'item_name'              => $w->item_name,
                    'worker_name'            => $w->worker_name,
                    'qty'                    => $qty,
                    'uom'                    => $w->uom ?: 'SAK',
                    'rate_type'              => $appliedType,
                    'rate'                   => $appliedRate,
                    'regular_rate'           => $regularRate,
                    'overtime_rate'          => $overtimeRate,
                    'regular_fee'            => $regularFee,
                    'lembur_fee'             => $lemburFee,
                    'total_fee'              => $activeFee,
                    'is_overtime'            => $appliedType === LoadingRateType::TYPE_OVERTIME,
                    'is_estimated_from_item' => false,
                    'notes'                  => $w->notes,
                ];

                $formattedWorkers[] = $workerRow;

                if ($detailed) {
                    // Group by worker name
                    if (!isset($byWorkerGroup[$wName])) {
                        $byWorkerGroup[$wName] = [
                            'worker_name'       => $wName,
                            'total_qty'         => 0.0,
                            'items_handled'     => 0,
                            'item_codes'        => [],
                            'total_regular_fee' => 0.0,
                            'total_lembur_fee'  => 0.0,
                            'total_fee'         => 0.0,
                            'rate_type'         => $appliedType,
                        ];
                    }
                    $byWorkerGroup[$wName]['total_qty'] += $qty;
                    $byWorkerGroup[$wName]['items_handled'] += 1;
                    $byWorkerGroup[$wName]['item_codes'][] = $itemCode;
                    $byWorkerGroup[$wName]['total_regular_fee'] += $regularFee;
                    $byWorkerGroup[$wName]['total_lembur_fee'] += $lemburFee;
                    $byWorkerGroup[$wName]['total_fee'] += $activeFee;

                    // Group by item code
                    if (!isset($byItemGroup[$itemCode])) {
                        $byItemGroup[$itemCode] = [
                            'item_code'     => $itemCode,
                            'item_name'     => $w->item_name,
                            'uom'           => $w->uom ?: 'SAK',
                            'total_qty'     => 0.0,
                            'regular_rate'  => $regularRate,
                            'overtime_rate' => $overtimeRate,
                            'total_fee'     => 0.0,
                            'workers_count' => 0,
                        ];
                    }
                    $byItemGroup[$itemCode]['total_qty'] += $qty;
                    $byItemGroup[$itemCode]['total_fee'] += $activeFee;
                    $byItemGroup[$itemCode]['workers_count'] += 1;
                }
            }
        } else {
            // Case B: Worker array was not filled in.
            // Automatically calculate worker/loading fees based on picklist items and their start_time!
            foreach ($picklist->items as $pItem) {
                $itemCode = (string) $pItem->item_code;
                $qty = (float) ($pItem->checked_qty > 0 ? $pItem->checked_qty : ($pItem->pick_qty > 0 ? $pItem->pick_qty : $pItem->ordered_qty));
                if ($qty <= 0) {
                    continue;
                }

                $uom = $pItem->unit_msr ?: ($pItem->item?->sal_unit_msr ?? 'SAK');

                // Determine time and rate type specifically from item's start_time
                $itemTimeStr = null;
                $timeSource = '';
                if (!empty($pItem->start_time)) {
                    $itemTimeStr = Carbon::parse($pItem->start_time)->format('H:i:s');
                    $timeSource = "start_time ({$itemTimeStr})";
                } elseif (!empty($pItem->end_time)) {
                    $itemTimeStr = Carbon::parse($pItem->end_time)->format('H:i:s');
                    $timeSource = "end_time ({$itemTimeStr})";
                } elseif (!empty($pItem->checked_at)) {
                    $itemTimeStr = Carbon::parse($pItem->checked_at)->format('H:i:s');
                    $timeSource = "checked_at ({$itemTimeStr})";
                } else {
                    $itemTimeStr = $determination['reference_time'];
                    $timeSource = "waktu operasional ({$itemTimeStr})";
                }

                $timeEvaluation = $this->determineTimeRateType($itemTimeStr, $rateTypes);
                $lineRateType = $timeEvaluation['rate_type'];
                $lineIsOt = $timeEvaluation['is_overtime'];

                // Lookup master rates for this item
                $regModel = $masterRates->first(function ($r) use ($itemCode) {
                    return $r->item_code === $itemCode &&
                        (strtoupper($r->rate_type) === LoadingRateType::TYPE_REGULAR || strtoupper($r->rate_type) === 'REGULER');
                });
                $regularRate = $regModel ? (float) $regModel->rate : 0.0;

                $otModel = $masterRates->first(function ($r) use ($itemCode) {
                    return $r->item_code === $itemCode &&
                        (strtoupper($r->rate_type) === LoadingRateType::TYPE_OVERTIME || strtoupper($r->rate_type) === 'LEMBUR');
                });
                $overtimeRate = $otModel ? (float) $otModel->rate : 0.0;

                $regularFee = round($qty * $regularRate, 2);
                $lemburFee = round($qty * $overtimeRate, 2);

                if ($lineIsOt) {
                    $appliedRate = $overtimeRate > 0 ? $overtimeRate : $regularRate;
                    $appliedType = LoadingRateType::TYPE_OVERTIME;
                    $activeFee = $lemburFee;
                } else {
                    $appliedRate = $regularRate > 0 ? $regularRate : $overtimeRate;
                    $appliedType = LoadingRateType::TYPE_REGULAR;
                    $activeFee = $regularFee;
                }

                $totalQty += $qty;
                $totalRegularAmount += $regularFee;
                $totalOvertimeAmount += $lemburFee;
                $totalFee += $activeFee;

                $wName = !empty($pItem->checked_by_checker)
                    ? trim((string) $pItem->checked_by_checker)
                    : (!empty($picklist->checker_name) ? trim((string) $picklist->checker_name) : 'Regu Muat / Kuli');
                $workerNames[$wName] = true;

                $workerRow = [
                    'id'                     => null,
                    'item_code'              => $itemCode,
                    'item_name'              => $pItem->item_name,
                    'worker_name'            => $wName,
                    'qty'                    => $qty,
                    'uom'                    => $uom,
                    'rate_type'              => $appliedType,
                    'rate'                   => $appliedRate,
                    'regular_rate'           => $regularRate,
                    'overtime_rate'          => $overtimeRate,
                    'regular_fee'            => $regularFee,
                    'lembur_fee'             => $lemburFee,
                    'total_fee'              => $activeFee,
                    'is_overtime'            => $lineIsOt,
                    'start_time'             => $pItem->start_time ? Carbon::parse($pItem->start_time)->toIso8601String() : null,
                    'is_estimated_from_item' => true,
                    'notes'                  => "Dihitung otomatis berdasarkan {$timeSource}",
                ];

                $formattedWorkers[] = $workerRow;

                if ($detailed) {
                    // Group by worker name
                    if (!isset($byWorkerGroup[$wName])) {
                        $byWorkerGroup[$wName] = [
                            'worker_name'       => $wName,
                            'total_qty'         => 0.0,
                            'items_handled'     => 0,
                            'item_codes'        => [],
                            'total_regular_fee' => 0.0,
                            'total_lembur_fee'  => 0.0,
                            'total_fee'         => 0.0,
                            'rate_type'         => $appliedType,
                        ];
                    }
                    $byWorkerGroup[$wName]['total_qty'] += $qty;
                    $byWorkerGroup[$wName]['items_handled'] += 1;
                    $byWorkerGroup[$wName]['item_codes'][] = $itemCode;
                    $byWorkerGroup[$wName]['total_regular_fee'] += $regularFee;
                    $byWorkerGroup[$wName]['total_lembur_fee'] += $lemburFee;
                    $byWorkerGroup[$wName]['total_fee'] += $activeFee;

                    // Group by item code
                    if (!isset($byItemGroup[$itemCode])) {
                        $byItemGroup[$itemCode] = [
                            'item_code'     => $itemCode,
                            'item_name'     => $pItem->item_name,
                            'uom'           => $uom,
                            'total_qty'     => 0.0,
                            'regular_rate'  => $regularRate,
                            'overtime_rate' => $overtimeRate,
                            'total_fee'     => 0.0,
                            'workers_count' => 1,
                        ];
                    }
                    $byItemGroup[$itemCode]['total_qty'] += $qty;
                    $byItemGroup[$itemCode]['total_fee'] += $activeFee;
                }
            }
        }

        // Clean arrays
        if ($detailed) {
            foreach ($byWorkerGroup as &$bg) {
                $bg['total_qty'] = round($bg['total_qty'], 4);
                $bg['total_regular_fee'] = round($bg['total_regular_fee'], 2);
                $bg['total_lembur_fee'] = round($bg['total_lembur_fee'], 2);
                $bg['total_fee'] = round($bg['total_fee'], 2);
                $bg['item_codes'] = array_values(array_unique($bg['item_codes']));
            }
            unset($bg);

            foreach ($byItemGroup as &$ig) {
                $ig['total_qty'] = round($ig['total_qty'], 4);
                $ig['total_fee'] = round($ig['total_fee'], 2);
            }
            unset($ig);
        }

        $summary = [
            'has_explicit_workers'  => $hasExplicitWorkers,
            'calculation_basis'     => $hasExplicitWorkers ? 'workers' : 'start_time',
            'determined_rate_type'  => $determinedType,
            'is_overtime'           => $isOvertime,
            'start_time'            => $determination['start_time'],
            'end_time'              => $determination['end_time'],
            'reference_time'        => $determination['reference_time'],
            'reference_timestamp'   => $determination['reference_timestamp'],
            'rate_type_source'      => $determination['reason'],
            'total_workers'         => count($workerNames),
            'total_worker_records'  => count($formattedWorkers),
            'total_qty'             => round($totalQty, 4),
            'total_regular_amount'  => round($totalRegularAmount, 2),
            'total_overtime_amount' => round($totalOvertimeAmount, 2),
            'total_fee'             => round($totalFee, 2),
        ];

        return [
            'summary'             => $summary,
            'workers'             => $formattedWorkers,
            'breakdown_by_worker' => array_values($byWorkerGroup),
            'breakdown_by_item'   => array_values($byItemGroup),
        ];
    }
}
