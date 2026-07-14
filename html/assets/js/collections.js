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

    $(function(){
        // Objectives: search + pagination
        var perPage = 10;
        var $tbody = $("#objectivesTable tbody");
        if (!$tbody.length) return;
        var allRows = $tbody.find("tr").toArray();
        var filtered = allRows.slice();
        var page = 1;
        function applyPage(){
            var total = filtered.length, pages = Math.ceil(total / perPage) || 1; if (page > pages) page = pages;
            $tbody.children().hide(); if (total){ var s=(page-1)*perPage, e=s+perPage; $(filtered.slice(s,e)).show(); }
            var $p = $("#objectivesPagination"); if (total>perPage){ $p.removeAttr('hidden'); $p.find('.qpInfo').text('Page '+page+' of '+pages);} else { $p.attr('hidden', true); }
        }
        function filterRows(){ var term=($("#objectivesSearch").val()||'').toLowerCase(); filtered = allRows.filter(function(tr){ return $(tr).text().toLowerCase().indexOf(term)!==-1; }); page=1; applyPage(); }
        filterRows();
        $("#objectivesSearch").on('input', filterRows);
        $(".objClear").on('click', function(){ $("#objectivesSearch").val(''); filterRows(); $("#objectivesSearch").trigger('focus'); });
        $("#objectivesPagination").on('click', '.qpBtn', function(){ var act=$(this).data('act'); var total=filtered.length; var pages=Math.ceil(total/perPage)||1; if(act==='prev'&&page>1)page--; if(act==='next'&&page<pages)page++; applyPage(); });
    });

    $(function(){
        // Monster names: search + pagination
        var perPage = 10;
        var $tbody = $("#monstersTable tbody");
        if (!$tbody.length) return;
        var allRows = $tbody.find("tr").toArray();
        var filtered = allRows.slice();
        var page = 1;
        function applyPage(){
            var total = filtered.length, pages = Math.ceil(total / perPage) || 1; if (page > pages) page = pages;
            $tbody.children().hide(); if (total){ var s=(page-1)*perPage, e=s+perPage; $(filtered.slice(s,e)).show(); }
            var $p = $("#monstersPagination"); if (total>perPage){ $p.removeAttr('hidden'); $p.find('.qpInfo').text('Page '+page+' of '+pages);} else { $p.attr('hidden', true); }
        }
        function filterRows(){ var term=($("#monstersSearch").val()||'').toLowerCase(); filtered = allRows.filter(function(tr){ return $(tr).text().toLowerCase().indexOf(term)!==-1; }); page=1; applyPage(); }
        filterRows();
        $("#monstersSearch").on('input', filterRows);
        $(".monClear").on('click', function(){ $("#monstersSearch").val(''); filterRows(); $("#monstersSearch").trigger('focus'); });
        $("#monstersPagination").on('click', '.qpBtn', function(){ var act=$(this).data('act'); var total=filtered.length; var pages=Math.ceil(total/perPage)||1; if(act==='prev'&&page>1)page--; if(act==='next'&&page<pages)page++; applyPage(); });
    });