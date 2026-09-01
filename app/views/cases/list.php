<?php
//debug($data['data']);
$page_link_prefix = "cases/list";
$aa = (($data['page']-1)*$data['items'])+1;

$suffix_terms = [];
if(isset($data['search'])&&($data['search']!="")){
    $suffix_terms[] = "search-term=".$data['search'] ;
}
foreach ($data['filter_data'] as $filter_data => $value){
    if($value!=""){
        $suffix_terms[] = $filter_data."=".$value;
    }
}

$page_link_suffix = (count($suffix_terms)>0) ? "?".implode("&", $suffix_terms) : "";

function check_list($all_R, $folder){
    if($folder=="%"){
        return (!isset($all_R->url['query']['idStatus'])||($all_R->url['query']['idStatus']==$folder)) ? "btn-primary" : "btn-default";
    } else {
        return (isset($all_R->url['query']['idStatus'])&&($all_R->url['query']['idStatus']==$folder)) ? "btn-primary" : "btn-default";
    }
}

?>
<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Manage Cases</h2>
    <div class="right-wrapper">
        <ol class="breadcrumbs">
            <li>
                <span><?= (isset($data['count'])) ? "Total of " . display($data['count']) . " entries" : "No entries yet"; ?></span>
            </li>
        </ol>
    </div>
</header>
<!-- start: page -->
<div class="row">
    <div class="col">
        <div class="card card-modern">
            <div class="card-body">
                <div class="datatables-header-footer-wrapper">
                    <div class="datatable-header">
                        <form method="get" action="<?=$this->L($page_link_prefix);?>">
                            <div class="row align-items-center mb-3">
                                <?php
                                $html = "";

                                if((isset($data['meta_filters']))&&(count($data['meta_filters'])>0)){
                                    foreach ($data['meta_filters'] as $filter){
                                        $filter_value =
                                        $html .= filter_DropDown($filter['key'], $filter, $data['filter_data'][$filter['key']]);
                                    }
                                }
                                print $html;

                                ?>
                                <div class="col-12 col-lg-auto ms-auto ml-auto mb-3 mb-lg-0">
                                    <div class="d-flex align-items-lg-center flex-column flex-lg-row"><label class="ws-nowrap me-3 mb-0">Constituency
                                        </label><select class="form-control select-style-1 filter-by" name="idConstituency" id="idConstituency"
                                                        onchange="javascript:this.form.submit()">
                                            <option value='%' <?=((isset($data['filter_data']['idConstituency']))&&($data['filter_data']['idConstituency']=='%')) ? "selected" : "";?>>All</option>
                                            <?php
                                            foreach ($data['available_constituencies'] as $constituency){
                                            ?>
                                            <option value='<?=$constituency['id'];?>' <?=((isset($data['filter_data']['idConstituency']))&&($constituency['id']==$data['filter_data']['idConstituency'])) ? "selected" : "";?>><?=$constituency['name'];?></option>
                                            <?php
                                            }
                                            ?>
                                        </select></div>
                                </div>
                                <div class="col-12 col-lg-auto ps-lg-1">

                                    <div class="search search-style-1 search-style-1-lg mx-lg-auto">
                                        <div class="input-group">
                                            <input type="text" class="search-term form-control" name="search-term"
                                                   id="search-term" placeholder="Search" value="">
                                            <button class="btn btn-default" type="submit"><i class="bx bx-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="btn-group-vertical flex-wrap d-flex">
                                <button type="button" class="btn <?=check_list($this->R, "%");?> w-100" onclick="window.location.href='?idStatus=%';">Inbox</button>
                                <button type="button" class="btn <?=check_list($this->R, "10");?> w-100" onclick="window.location.href='?idStatus=10';">New</button>
                                <button type="button" class="btn <?=check_list($this->R, "20");?> w-100" onclick="window.location.href='?idStatus=20';">Open</button>
                                <button type="button" class="btn <?=check_list($this->R, "30");?> w-100" onclick="window.location.href='?idStatus=30';">Rejected</button>
                                <button type="button" class="btn <?=check_list($this->R, "40");?> w-100" onclick="window.location.href='?idStatus=40';">Closed</button>
                            </div>
                        </div>
                        <div class="col-md-10 table-responsive">
                            <?php
                            if(count($data['data'])>0){
                            ?>
                            <table id="table" class="table table-responsive-lg table-striped table-sm mb-0" style="width:100%">
                                <thead>
                                <tr>
                                    <th width="2%"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value="" /></th>
                                    <th width="2%">A/A</th>
                                    <?php
                                    foreach ($data['fields'] as $field => $properties){
                                        if(isset($properties['appear_in_list'])){
                                            $title = (isset($properties['title'])) ? $properties['title'] : $field;
                                            ?>
                                            <th width="<?=$properties['list_width'];?>%"><?=ucfirst($title)?></th>
                                            <?php
                                        }
                                    }
                                    ?>
                                    <th width="1%">&nbsp;</th>
                                </tr>
                                </thead>
                                <tbody>

                                <?php
                                foreach ($data['data'] as $row) {
                                    $link = "cases/edit/".$row['id'];
                                    ?>
                                    <tr>
                                        <td width="2%"><input type="checkbox" name="checkboxRow1" class="checkbox-style-1 p-relative top-2" value="" /></td>
                                        <td><?=$aa;?></td>
                                        <?php
                                        $first = true;
                                        foreach ($data['fields'] as $field => $properties) {
                                            if (isset($properties['appear_in_list'])) {

                                                $active = (isset($row['active'])) ? $row['active'] : true;
                                                print ($first)
                                                    ? '<td><a href="' . $this->L($link) . '"><strong>' . display_list_element($properties, $row[$field], $active) . '</strong></a></td>'
                                                    : '<td>' . display_list_element($properties, $row[$field], $active) . '</td>';
                                                $first = false;
                                            }
                                        }
                                        ?>
                                        <td><a href="<?=$this->L($link);?>"><i class="bx bxs-edit bx-sm"></i></a></td>
                                    </tr>
                                    <?php
                                    $aa++;
                                }
                                ?>

                                </tbody>
                                <tfoot>
                                <tr>
                                    <th width="2%"><input type="checkbox" name="select-all" class="select-all checkbox-style-1 p-relative top-2" value="" /></th>
                                    <th width="2%">A/A</th>
                                    <?php
                                    foreach ($data['fields'] as $field => $properties){
                                        if(isset($properties['appear_in_list'])){
                                            $title = (isset($properties['title'])) ? $properties['title'] : $field;
                                            ?>
                                            <th width="<?=$properties['list_width'];?>%"><?=ucfirst($title)?></th>
                                            <?php
                                        }
                                    }
                                    ?>
                                    <th width="1%">&nbsp;</th>
                                </tr>
                                </tfoot>
                            </table>
                            <?php
                            }
                            ?>
                        </div>
                            <hr class="solid mt-5 opacity-4">
                            <div class="datatable-footer">
                                <div class="row align-items-center justify-content-between mt-3">
                                    <!--                            <div class="col-md-auto order-1 mb-3 mb-lg-0">-->
                                    <!--                                <div class="d-flex align-items-stretch">-->
                                    <!--                                    <div class="d-grid gap-3 d-md-flex justify-content-md-end me-4">-->
                                    <!--                                        <select class="form-control select-style-1 bulk-action" name="bulk-action" style="min-width: 170px;">-->
                                    <!--                                            <option value="" selected>Bulk Actions</option>-->
                                    <!--                                            <option value="delete">Delete</option>-->
                                    <!--                                        </select>-->
                                    <!--                                        <a href="ecommerce-orders-detail.html" class="bulk-action-apply btn btn-light btn-px-4 py-3 border font-weight-semibold text-color-dark text-3">Apply</a>-->
                                    <!--                                    </div>-->
                                    <!--                                </div>-->
                                    <!--                            </div>-->
                                    <div class="col-lg-auto text-center order-3 order-lg-2">
                                        <div class="results-info-wrapper">Showing <?=(($data['page']-1)*$data['items'])+1;?> to <?=($aa-1);?> of <?=$data['count'];?> entries
                                        </div>
                                    </div>
                                    <div class="col-lg-auto order-2 order-lg-3 mb-3 mb-lg-0">
                                        <div class="pagination-wrapper">
                                            <div class="dataTables_paginate paging_simple_numbers" id="datatable-list_paginate">
                                                <ul class="pagination pagination-modern pagination-modern-spacing justify-content-center">
                                                    <?php
                                                    $last_page = ceil($data['count']/$data['items']);
                                                    $previous_disabled = ($data['page']==1) ? "disabled" : "";
                                                    $next_disabled = ($data['page']==$last_page) ? "disabled" : "";
                                                    $start_from = (($data['page']-2)<1) ? 1 : $data['page']-2;
                                                    $end_to = (($data['page']+2)>$last_page) ? $last_page : $data['page']+2;
                                                    ?>
                                                    <li class="paginate_button page-item previous <?=$previous_disabled;?>"><a href="<?=$this->L($page_link_prefix.$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevrons-left' ></i></a></li>
                                                    <li class="paginate_button page-item previous <?=$previous_disabled;?>"><a href="<?=$this->L($page_link_prefix."/".($data['page']-1).$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevron-left' ></i></a></li>
                                                    <?php
                                                    if($start_from>=2){ print '<li class="paginate_button page-item previous disabled"><a href="#" class="page-link"><i class="bx bx-dots-horizontal-rounded" ></i></a></li>';}
                                                    for ($page_num = $start_from; $page_num <= $end_to; $page_num++){
                                                        $active_page = ($data['page']==$page_num) ? "active" : "";
                                                        print '<li class="paginate_button page-item '.$active_page.'"><a href="'.$this->L($page_link_prefix."/".$page_num.$page_link_suffix).'" class="page-link">'.$page_num.'</a></li>';
                                                    }
                                                    if($end_to<=$last_page-1){ print '<li class="paginate_button page-item next disabled"><a href="#" class="page-link"><i class="bx bx-dots-horizontal-rounded" ></i></a></li>';}
                                                    ?>
                                                    <li class="paginate_button page-item next <?=$next_disabled;?>"><a href="<?=$this->L($page_link_prefix."/".($data['page']+1).$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevron-right' ></i></a></li>
                                                    <li class="paginate_button page-item next <?=$next_disabled;?>"><a href="<?=$this->L($page_link_prefix."/".$last_page.$page_link_suffix);?>" class="page-link"><i class='bx bxs-chevrons-right' ></i></a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>