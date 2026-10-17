<?php
// page links under a table. the panel around it needs an id and a data-paginated
// attribute so ajax.js can swap just that panel instead of the whole page. expects:
//   $pagination     - the array returned by paginate()
//   $paginationBase - the page url with its current filters, e.g. "transactions.php?type=all"
//   $paginationNoun - what's being counted, e.g. "transactions"

$paginationBase = $paginationBase ?? '';
$paginationNoun = $paginationNoun ?? 'results';
$joiner = strpos($paginationBase, '?') === false ? '?' : '&';
$current = $pagination['page'];
$last = $pagination['totalPages'];

$pageUrl = function (int $page) use ($paginationBase, $joiner): string {
    return $paginationBase . $joiner . 'page=' . $page;
};

// always show the first and last page, plus a couple either side of the current one
$pageNumbers = [];
for ($i = 1; $i <= $last; $i++) {
    if ($i === 1 || $i === $last || abs($i - $current) <= 1) {
        $pageNumbers[] = $i;
    }
}
?>
<div class="pagination-bar">
    <span class="pagination-summary">
        <?php if ($pagination['total'] > 0): ?>
            Showing <strong><?php echo $pagination['from']; ?>–<?php echo $pagination['to']; ?></strong>
            of <strong><?php echo $pagination['total']; ?></strong> <?php echo sanitiseOutput($paginationNoun); ?>
        <?php endif; ?>
    </span>

    <?php if ($last > 1): ?>
        <nav class="pagination" aria-label="Pagination">
            <?php if ($current > 1): ?>
                <a href="<?php echo sanitiseOutput($pageUrl($current - 1)); ?>" class="pagination-link js-page-link" aria-label="Previous page">
                    <i class="fas fa-chevron-left" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="pagination-link is-disabled" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php $previous = 0; ?>
            <?php foreach ($pageNumbers as $number): ?>
                <?php if ($number - $previous > 1): ?>
                    <span class="pagination-gap" aria-hidden="true">…</span>
                <?php endif; ?>

                <?php if ($number === $current): ?>
                    <span class="pagination-link is-current" aria-current="page"><?php echo $number; ?></span>
                <?php else: ?>
                    <a href="<?php echo sanitiseOutput($pageUrl($number)); ?>" class="pagination-link js-page-link" aria-label="Page <?php echo $number; ?>"><?php echo $number; ?></a>
                <?php endif; ?>

                <?php $previous = $number; ?>
            <?php endforeach; ?>

            <?php if ($current < $last): ?>
                <a href="<?php echo sanitiseOutput($pageUrl($current + 1)); ?>" class="pagination-link js-page-link" aria-label="Next page">
                    <i class="fas fa-chevron-right" aria-hidden="true"></i>
                </a>
            <?php else: ?>
                <span class="pagination-link is-disabled" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</div>
