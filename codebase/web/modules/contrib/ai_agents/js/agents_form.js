(function ($) {
  let timeout;

  document.addEventListener('DOMContentLoaded', () => {
    // Prevent form submission on enter
    $('#edit-filter').on('keypress', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        return false;
      }
    });

    // Filter tools on keyup
    $('#edit-filter').on('keyup', (e) => {
      // Enter key should do nothing
      if (e.key === 'Enter') {
        e.preventDefault();
        return;
      }
      // Make sure that the timeout is cleared.
      if (timeout) {
        clearTimeout(timeout);
      }
      // Make sure to debounce 500ms.
      timeout = setTimeout(() => {
        const filterValue = document
          .querySelector('#edit-filter')
          .value.toLowerCase()
          .trim();

        $('.tool-wrapper').each(function () {
          const toolId = $(this).data('id').toLowerCase();
          const toolTitle = $(this).find('label')[0].textContent.toLowerCase();

          // Show if matches either ID or title
          if (toolId.includes(filterValue) || toolTitle.includes(filterValue)) {
            $(this).show();
          } else {
            $(this).hide();
          }
        });
      }, 500);
    });
  });
})(jQuery);
