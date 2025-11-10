document.querySelectorAll('.favorite-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                this.classList.toggle('active');
            });
        });

        document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const itemName = this.closest('.favorite-item').querySelector('.item-name').textContent;
                alert(`Added ${itemName} to cart!`);
            });
        });

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const label = this.closest('.detail-row').querySelector('.detail-label').textContent;
                alert(`Editing ${label}`);
            });
        });