(() => {
    'use strict';

    const initCommunityPage = () => {
        const content = document.querySelector('.communityContent');
        const toc = document.getElementById('communityTocList');
        const searchInput = document.getElementById('communitySearchInput');
        if (!content || !toc || !searchInput) return;

        const headings = [...content.querySelectorAll('h1[id], h2[id]')];
        headings.forEach(heading => {
            const item = document.createElement('li');
            item.className = `communityTocLevel${heading.tagName.slice(1)}`;
            const link = document.createElement('a');
            link.href = `#${heading.id}`;
            link.className = 'communityTocLink';
            const title = document.createElement('span');
            title.className = 'communityTocLinkTitle';
            title.textContent = heading.cloneNode(true).textContent.replace(/#$/, '').trim();
            const description = document.createElement('span');
            description.className = 'communityTocDescription';
            description.textContent = heading.dataset.tocDescription || '';
            if (description.textContent) item.classList.add('hasDescription');
            link.append(title, description);
            item.append(link);
            toc.append(item);
        });
        if (!headings.length) document.querySelector('.communityToc').hidden = true;

        const previousButton = document.getElementById('communitySearchPrevious');
        const nextButton = document.getElementById('communitySearchNext');
        const clearButton = document.getElementById('communitySearchClear');
        const status = document.getElementById('communitySearchStatus');
        const searchDock = document.getElementById('communitySearchDock');
        const searchPanel = document.getElementById('communitySearchPanel');
        const searchToggle = document.getElementById('communitySearchToggle');
        const tocPanel = document.querySelector('.communityToc');
        const tocHeader = document.querySelector('.communityTocHeader');
        const communityDocument = document.querySelector('.communityDocument');
        let matches = [];
        let currentMatch = -1;

        const updateStickySearch = () => {
            const shouldStick = searchDock.classList.contains('open') &&
                tocPanel.getBoundingClientRect().top < 10 &&
                communityDocument.getBoundingClientRect().bottom > 90;
            searchDock.classList.toggle('isSticky', shouldStick);
        };

        const setSearchOpen = open => {
            searchDock.classList.toggle('open', open);
            tocHeader.classList.toggle('searchOpen', open);
            searchToggle.setAttribute('aria-expanded', String(open));
            searchToggle.textContent = open ? 'Close' : 'Search';
            searchPanel.inert = !open;
            if (open) searchInput.focus({preventScroll: true});
            else searchDock.classList.remove('isSticky');
            updateStickySearch();
        };

        searchToggle.addEventListener('click', () => setSearchOpen(!searchDock.classList.contains('open')));
        window.addEventListener('scroll', updateStickySearch, {passive: true});
        window.addEventListener('resize', updateStickySearch);

        const clearMarks = () => {
            content.querySelectorAll('mark.communitySearchMatch').forEach(mark => {
                mark.replaceWith(document.createTextNode(mark.textContent));
            });
            content.normalize();
            matches = [];
            currentMatch = -1;
        };

        const showMatch = index => {
            if (!matches.length) return;
            matches.forEach(match => match.classList.remove('current'));
            currentMatch = (index + matches.length) % matches.length;
            matches[currentMatch].classList.add('current');
            matches[currentMatch].scrollIntoView({behavior: 'smooth', block: 'center'});
            searchInput.focus({preventScroll: true});
            status.textContent = `Match ${currentMatch + 1} of ${matches.length}`;
        };

        const runSearch = () => {
            clearMarks();
            const query = searchInput.value.trim();
            clearButton.disabled = query === '';
            if (query === '') {
                status.textContent = '0 matches';
                previousButton.disabled = true;
                nextButton.disabled = true;
                return;
            }

            const lowerQuery = query.toLocaleLowerCase();
            const walker = document.createTreeWalker(content, NodeFilter.SHOW_TEXT, {
                acceptNode(node) {
                    if (!node.nodeValue.toLocaleLowerCase().includes(lowerQuery)) return NodeFilter.FILTER_REJECT;
                    if (node.parentElement?.closest('a, button, script, style, mark')) return NodeFilter.FILTER_REJECT;
                    return NodeFilter.FILTER_ACCEPT;
                }
            });
            const textNodes = [];
            while (walker.nextNode()) textNodes.push(walker.currentNode);

            textNodes.forEach(node => {
                const text = node.nodeValue;
                const lowerText = text.toLocaleLowerCase();
                const fragment = document.createDocumentFragment();
                let offset = 0;
                let matchIndex;
                while ((matchIndex = lowerText.indexOf(lowerQuery, offset)) !== -1) {
                    fragment.append(document.createTextNode(text.slice(offset, matchIndex)));
                    const mark = document.createElement('mark');
                    mark.className = 'communitySearchMatch';
                    mark.textContent = text.slice(matchIndex, matchIndex + query.length);
                    fragment.append(mark);
                    offset = matchIndex + query.length;
                }
                fragment.append(document.createTextNode(text.slice(offset)));
                node.replaceWith(fragment);
            });

            matches = [...content.querySelectorAll('mark.communitySearchMatch')];
            previousButton.disabled = matches.length === 0;
            nextButton.disabled = matches.length === 0;
            if (matches.length) showMatch(0);
            else status.textContent = `0 matches for "${query}"`;
        };

        searchInput.addEventListener('input', runSearch);
        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Enter' && matches.length) {
                event.preventDefault();
                showMatch(currentMatch + (event.shiftKey ? -1 : 1));
            }
            if (event.key === 'Escape') {
                if (searchInput.value !== '') clearButton.click();
                else setSearchOpen(false);
            }
        });
        previousButton.addEventListener('click', () => showMatch(currentMatch - 1));
        nextButton.addEventListener('click', () => showMatch(currentMatch + 1));
        clearButton.addEventListener('click', () => {
            searchInput.value = '';
            clearMarks();
            clearButton.disabled = true;
            previousButton.disabled = true;
            nextButton.disabled = true;
            status.textContent = '0 matches';
            searchInput.focus();
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCommunityPage, {once: true});
    } else {
        initCommunityPage();
    }
})();
