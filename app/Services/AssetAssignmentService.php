<?php

namespace App\Services;

use App\Enum\AssetStatus;
use App\Enum\AssignmentType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Hands assets out to employees and takes them back, keeping the asset's
 * status + current holder in lock-step with the assignment ledger. A "borrow"
 * is just an assignment with a due date and an eventual return.
 */
class AssetAssignmentService
{
    public function __construct(private RequestNotifier $notifier) {}

    /**
     * Assign (or lend) an asset to an employee and notify them.
     *
     * @throws RuntimeException when the asset is already held by someone.
     */
    public function assign(
        Asset $asset,
        User $user,
        AssignmentType $type = AssignmentType::PERMANENT,
        ?CarbonInterface $dueAt = null,
        ?string $notes = null,
        ?User $assignedBy = null,
    ): AssetAssignment {
        if ($asset->currentAssignment()->exists()) {
            throw new RuntimeException('This asset is already assigned. Return it first.');
        }

        $assignment = DB::transaction(function () use ($asset, $user, $type, $dueAt, $notes, $assignedBy): AssetAssignment {
            $assignment = $asset->assignments()->create([
                'user_id' => $user->id,
                'type' => $type,
                'assigned_at' => now(),
                'due_at' => $type === AssignmentType::BORROW ? $dueAt : null,
                'assigned_by' => $assignedBy?->id,
                'notes' => $notes,
            ]);

            $asset->forceFill([
                'status' => $type->resultingAssetStatus(),
                'assigned_to' => $user->id,
            ])->save();

            return $assignment;
        });

        $this->notifier->assetAssigned($assignment);

        return $assignment;
    }

    /**
     * Return the asset currently held, closing its open assignment and freeing
     * the unit. Returns the closed assignment, or null if nothing was open.
     */
    public function return(Asset $asset, ?string $notes = null, ?User $receivedBy = null): ?AssetAssignment
    {
        $assignment = $asset->currentAssignment()->first();

        if ($assignment === null) {
            return null;
        }

        return DB::transaction(function () use ($asset, $assignment, $notes, $receivedBy): AssetAssignment {
            $assignment->forceFill([
                'returned_at' => now(),
                'received_by' => $receivedBy?->id,
                'notes' => $notes !== null && $notes !== ''
                    ? trim(($assignment->notes ? $assignment->notes."\n" : '').$notes)
                    : $assignment->notes,
            ])->save();

            $asset->forceFill([
                'status' => AssetStatus::AVAILABLE,
                'assigned_to' => null,
            ])->save();

            return $assignment;
        });
    }
}
