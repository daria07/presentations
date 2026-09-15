const btn=document.querySelector('.menu-btn');const nav=document.querySelector('.nav');btn?.addEventListener('click',()=>{const isOpen=getComputedStyle(nav).display!=='none';nav.style.display=isOpen?'none':'flex';nav.style.flexDirection='column';nav.style.position='absolute';nav.style.top='76px';nav.style.left='16px';nav.style.right='16px';nav.style.background='#fff';nav.style.padding='18px';nav.style.border='1px solid #ece8f7';nav.style.borderRadius='18px';nav.style.boxShadow='0 12px 24px rgba(0,0,0,.06)';});

/*
   Метки из рекламы переносим на кнопку: человек уходит на /register,
   и без этого utm_source терялся бы ровно на том шаге, по которому
   и считают, какой лендинг сработал.
*/
if (window.location.search) {
    document.querySelectorAll('a.cta-link').forEach(function (link) {
        var url = new URL(link.getAttribute('href'), window.location.origin);

        new URLSearchParams(window.location.search).forEach(function (value, key) {
            url.searchParams.set(key, value);
        });

        link.setAttribute('href', url.pathname + url.search);
    });
}
