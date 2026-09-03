<?php
//debug($data['data']);
?>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <?php
                    // !empty rather than count(): the controller leaves data unset
                    // for an unknown project, and count(null) is a TypeError.
                    if(!empty($data['data'])){
                        ?>
                        <div class="table-responsive">
                            <table class="table table-ecommerce-simple table-borderless table-striped mb-0" id="datatable-list" style="min-width: 640px;">
                                <thead>
                                <tr>
                                    <th width="4%">#</th>
                                    <th>Task</th>
                                    <th width="18%">Status</th>
                                    <th width="22%"></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                // The partial is not paginated; page/items are only set on list pages.
                                $aa = (((int)($data['page'] ?? 1)) - 1) * ((int)($data['items'] ?? 0)) + 1;
                                foreach ($data['data'] as $row) {
                                    ?>
                                    <?php
                                    // A task is either delivered or not; say so in words and colour,
                                    // and make the thing to click look like a button. The word alone
                                    // used to be the only control, and nobody found it.
                                    $done  = ((int)($row['result'] ?? 0) === 1);
                                    $attrs = 'data-project-id="'.(int)$row['project_id'].'" data-member-id="'.(int)$row['member_id'].'" data-id="'.(int)$row['task_id'].'"';
                                    ?>
                                    <tr>
                                        <td><?=$aa;?></td>
                                        <td><strong><?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (!empty($row['description'])): ?>
                                                <div class="afcdc-progress-zero"><?= htmlspecialchars((string)$row['description'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($done): ?>
                                                <span class="afcdc-status afcdc-status--good"><i class="bx bx-check-circle"></i> Delivered</span>
                                            <?php else: ?>
                                                <span class="afcdc-status afcdc-status--idle"><i class="bx bx-time-five"></i> Not delivered</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="#" class="open-task-modal btn btn-sm <?= $done ? 'btn-light border' : 'btn-primary'; ?>" <?= $attrs; ?>>
                                                <i class="bx bx-edit"></i> <?= $done ? 'Change' : 'Record delivery'; ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php
                                    $aa++;
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    } else {
                        // Empty because the account's reporting entity is not in this
                        // activity's task "applies to", or the account is linked to no
                        // entity at all (member_id 0).
                        $ms = $_SESSION['user']['member_state'] ?? null;
                        ?>
                        <p class="text-muted py-4 mb-0">
                        <?php if (empty($ms)): ?>Your account is not linked to a reporting entity yet, so there is nothing to record here. Ask an administrator to link it.
                        <?php else: ?>No task for this activity applies to <?= display($ms['name'] ?? 'your entity'); ?>. If you expected to report on it, ask an administrator to add your entity under the task's "Applies to".<?php endif; ?>
                        </p>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="taskModal" class="modal-block modal-block-primary mfp-hide"></div>