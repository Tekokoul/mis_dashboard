<header class="page-header page-header-left-inline-breadcrumb">
    <h2 class="font-weight-bold text-6">Top Issues</h2>
</header>
<div class="row">
    <div class="col-lg-12 col-md-12">
        <section class="card">
            <div class="card-body">
                <h4>TOP ISSUES AT NATIONAL LEVEL</h4>
                <table width="100%">
                    <tr class="table-red-color">
                        <td><b>1</b></td>
                        <td><b>2</b></td>
                        <td><b>3</b></td>
                        <td><b>4</b></td>
                        <td><b>5</b></td>
                    </tr>
                    <tr>
                        <td class="table-color-blue-1"><?=$data['totals'][0]['title'] ?? "-"?></td>
                        <td class="table-color-blue-2"><?=$data['totals'][1]['title'] ?? "-"?></td>
                        <td class="table-color-blue-3"><?=$data['totals'][2]['title'] ?? "-"?></td>
                        <td class="table-color-blue-4"><?=$data['totals'][3]['title'] ?? "-"?></td>
                        <td class="table-color-blue-5"><?=$data['totals'][4]['title'] ?? "-"?></td>
                    </tr>
                </table>
                <h4>TOP ISSUES AT CONSTITUENCY LEVEL</h4>
                <table width="100%">
                    <?php
                    foreach ($data['constituencies'] as $constituency) {
                        ?>
                        <tr class="table-red-color">
                            <td><b><?= $constituency['name']; ?></b></td>
                        </tr>
                        <tr>
                            <td class="table-color-blue-1"><?=$constituency['issues'][0]['title'] ?? "-"?></td>
                            <td class="table-color-blue-2"><?=$constituency['issues'][1]['title'] ?? "-"?></td>
                            <td class="table-color-blue-3"><?=$constituency['issues'][2]['title'] ?? "-"?></td>
                            <td class="table-color-blue-4"><?=$constituency['issues'][3]['title'] ?? "-"?></td>
                            <td class="table-color-blue-5"><?=$constituency['issues'][4]['title'] ?? "-"?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </section>
    </div>
</div>