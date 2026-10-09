// Filter bar: klik label tanggal/bulan membuka picker bawaan dari input yang disembunyikan.
document.querySelectorAll('.fctl[data-picker]').forEach(function (l) {
    l.addEventListener('click', function (e) {
        e.preventDefault();
        var i = l.querySelector('input');
        try { i.showPicker(); } catch (err) { i.style.pointerEvents = 'auto'; i.focus(); }
    });
});
