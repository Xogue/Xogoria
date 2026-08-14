// Quote search, favorites, and pagination.
$(function(){
        var perPage = 10;
        var $table = $("#quotesAllTable");
        var $tbody = $table.find("tbody");
        var allRows = $tbody.find("tr").toArray();
        var filtered = allRows.slice();
        var page = 1;

        function applyPage(){
            var total = filtered.length;
            var pages = Math.ceil(total / perPage) || 1;
            if (page > pages) page = pages;
            $tbody.children().hide();
            if (total) {
                var start = (page - 1) * perPage;
                var end = start + perPage;
                $(filtered.slice(start, end)).show();
            }
            var $pager = $(".quotesPagination");
            if (total > perPage) {
                $pager.removeAttr('hidden');
                $pager.find('.qpInfo').text('Page ' + page + ' of ' + pages);
            } else {
                $pager.attr('hidden', true);
            }
        }

        function filterRows(){
            var term = ($("#quotesSearch").val() || '').toString().toLowerCase();
            var favOnly = $(".favPill").hasClass('active');
            filtered = allRows.filter(function(tr){
                var $tr = $(tr);
                if (favOnly && $tr.data('fav') != 1) return false;
                return $tr.text().toLowerCase().indexOf(term) !== -1;
            });
            page = 1;
            applyPage();
        }

        // init
        filterRows();
        $("#quotesSearch").on('input', filterRows);
        $(".qsClear").on('click', function(){ $("#quotesSearch").val(''); filterRows(); $("#quotesSearch").trigger('focus'); });
        $(".favPill").on('click', function(){
            var isActive = $(this).toggleClass('active').hasClass('active');
            $(this).attr('aria-pressed', isActive ? 'true' : 'false');
            filterRows();
        });
        $(".quotesPagination").on('click', '.qpBtn', function(){
            var act = $(this).data('act');
            var total = filtered.length;
            var pages = Math.ceil(total / perPage) || 1;
            if (act === 'prev' && page > 1) page--;
            if (act === 'next' && page < pages) page++;
            applyPage();
        });
    });
