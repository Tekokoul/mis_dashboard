<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Vouchers</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li><span><?= (count($data['data']))>0 ? "Total of ".display(count($data['data']))." entries" : "No entries yet"; ?></span></li>
        </ol>
    </div>
</header>
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <?php
                    if(count($data['data'])>0){
                        ?>
                        <table class="table table-ecommerce-simple table-borderless table-striped mb-0" id="datatable-list" style="min-width: 640px;">
                            <thead>
                            <tr>
                                <th width="3%"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value="" /></th>
                                <th width="4%">A/A</th>
                                <th width="5%">Reference</th>
                                <th width="35%">Name</th>
                                <th width="10%">Cash On Delivery</th>
                                <th width="10%">Tracking Nr.</th>
                                <th width="10%">Tracking Nr. Date</th>
                                <th width="5%">Printed</th>
                                <th width="5%">Listed</th>
                                <th width="10%">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                            $aa = 1;
                            foreach ($data['data'] as $row) {
//                                debug($row);
                                $link = "orders/edit/".$row['order_reference'];
                                ?>
                                <tr>
                                    <td width="30"><input type="checkbox" name="checkboxRow1" class="checkbox-style-1 p-relative top-2" value="" /></td>
                                    <td><?=$aa;?></td>
                                    <td><strong><a href="<?=$this->L($link);?>"><?=$row['order_reference'];?></a></strong></td>
                                    <td><?=$row['name'];?></td>
                                    <td><?=display_price_currency($row['cod']);?></td>
                                    <td><?=$row['tracking_number'];?></td>
                                    <td><?=display_time($row['tracking_number_date']);?></td>
                                    <td><?=($row['printed']) ? "Y" : "N";?></td>
                                    <td><?=($row['listed']) ? "Y" : "N";?></td>
                                    <td>
                                        <a href="<?=$this->L("vouchers/print/".$this->S['eshop']['courier']."/".$row['tracking_number']);?>" target="_blank"><i class='bx bxs-printer bx-sm'></i></a>
                                        <a class="voucher-delete" data-id="<?=$row['tracking_number'];?>" data-courier="<?=$this->S['eshop']['courier'];?>" href="#deleteModal"><i class='bx bx-trash bx-sm' ></i></a>
                                    </td>
                                </tr>
                                <?php
                                $aa++;
                            }
                            ?>
                            </tbody>
                        </table>

                        <hr class="solid mt-5 opacity-4">
                        <div class="datatable-footer">
                            <div class="row align-items-center justify-content-between mt-3">
                                <div class="col-md-auto order-1 mb-3 mb-lg-0">
                                    <div class="d-flex align-items-stretch">
                                        <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">
                                            <a href="<?=$this->L("vouchers/print_all/".$this->S['eshop']['courier'])?>" target="_blank" class="btn btn-default btn-px-4 py-3 line-height-1 me-2"><i class="bx bxs-printer text-4 me-2"></i> Print All</a>
                                            <a href="<?=$this->L("vouchers/finalize_list/".$this->S['eshop']['courier'])?>" target="_blank" class="btn btn-default btn-px-4 py-3 line-height-1 me-2"><i class="bx bx-list-ol text-4 me-2"></i>Finalize & Print List</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-auto text-center order-3 order-lg-2">
                                    <div class="results-info-wrapper">Showing <?=count($data['data']);?> entries
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="deleteModal" class="modal-block modal-block-primary mfp-hide" data-tablename="<?=display("store_vouchers")?>">
    <section class="card">
        <header class="card-header">
            <h2 class="card-title">Are you sure?</h2>
        </header>
        <div class="card-body">
            <div class="modal-wrapper">
                <div class="modal-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="modal-text">
                    <p class="mb-0">Are you sure that you want to delete this entry?</p>
                </div>
            </div>
        </div>
        <footer class="card-footer">
            <div class="row">
                <div class="col-md-12 text-end">
                    <button class="btn btn-primary modal-confirm">Confirm</button>
                    <button class="btn btn-default modal-dismiss">Cancel</button>
                </div>
            </div>
        </footer>
    </section>
</div>