/**
 * Live Search & Autocomplete Component
 */
document.addEventListener('DOMContentLoaded', () => {
  const searchInputs = document.querySelectorAll('.js-live-search');

  searchInputs.forEach(input => {
    let debounceTimer;
    const resultsContainer = document.createElement('div');
    resultsContainer.className = 'search-suggestions-dropdown';
    resultsContainer.style.cssText = `
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: var(--color-surface-card, #181830);
      border: 1px solid var(--color-border, rgba(255,255,255,0.1));
      border-radius: var(--radius-md, 10px);
      margin-top: 4px;
      max-height: 380px;
      overflow-y: auto;
      z-index: 999;
      display: none;
      box-shadow: var(--shadow-lg, 0 8px 32px rgba(0,0,0,0.4));
    `;
    
    // Parent wrapper must be relative
    if (input.parentElement) {
      input.parentElement.style.position = 'relative';
      input.parentElement.appendChild(resultsContainer);
    }

    input.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      const query = e.target.value.trim();

      if (query.length < 2) {
        resultsContainer.style.display = 'none';
        resultsContainer.innerHTML = '';
        return;
      }

      debounceTimer = setTimeout(async () => {
        try {
          const response = await fetch(`api/suggestions.php?query=${encodeURIComponent(query)}`);
          const results = await response.json();

          if (!results || results.length === 0) {
            resultsContainer.innerHTML = `<div style="padding: 12px; color: var(--color-text-muted); text-align: center; font-size: 14px;">Aucun produit trouvé pour "${query}"</div>`;
          } else {
            resultsContainer.innerHTML = results.map(item => {
              const name = typeof item === 'string' ? item : item.name;
              const id = typeof item === 'object' && item.id ? item.id : '';
              const price = typeof item === 'object' && item.price ? `${item.price} $` : '';
              const link = id ? `buy_product.php?id=${id}` : `recherche.php?q=${encodeURIComponent(name)}`;

              return `
                <a href="${link}" style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; color: var(--color-text, #fff); text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 14px; transition: background 0.15s;" onmouseover="this.style.background='var(--color-surface-hover)'" onmouseout="this.style.background='transparent'">
                  <span>${name}</span>
                  ${price ? `<span style="color: var(--color-primary, #6C5CE7); font-weight: 600;">${price}</span>` : ''}
                </a>
              `;
            }).join('');
          }
          resultsContainer.style.display = 'block';
        } catch (err) {
          console.error('Search error:', err);
        }
      }, 250);
    });

    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !resultsContainer.contains(e.target)) {
        resultsContainer.style.display = 'none';
      }
    });
  });
});
