<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Export Keys</h2>
</header>
<div class="row">
    <div class="col-12">
        <section class="card card-modern">
            <header class="card-header">
                <h2 class="card-title">Models</h2>
            </header>
            <div class="card-body">
                <table class="table table-responsive-md mb-0">
                    <thead>
                    <tr>
                        <th width="3%">#</th>
                        <th width="70%">Name</th>
                        <th width="15%">Nr of Keys</th>
                        <th width="7%">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i=1;
                    foreach ($data as $key => $value){
                        ?>
                    <tr>
                        <td><?=$i;?></td>
                        <td><?=$key?></td>
                        <td><?=$value?></td>
                        <td>
                            <div class="btn-group flex-wrap">
                                <a type="button" class="btn btn-default" href="<?=$this->L("tools/export_data_xls/".$key);?>" title="Excel"><i class="far fa-file-excel"></i></a>
<!--                                <a type="button" class="btn btn-default" title="PDF"><i class="far fa-file-pdf"></i></a>-->
<!--                                <a type="button" class="btn btn-default" title="CSV"><i class="far fa-file"></i></a>-->
                            </div>
                        </td>
                    </tr>
                    <?php
                        $i++;
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>