export default function (Alpine) {
    Alpine.store('listingAvailability', {
        items: {},
        references: {},
        url: '',
        timer: null,
        busy: false,
        stale: false,
        register(id, initial, url) {
            this.references[id] = (this.references[id] || 0) + 1;
            if (!this.items[id]) this.items[id] = initial;
            this.url = url;
            if (!this.timer) {
                this.timer = setInterval(() => this.refresh(), 5000);
                setTimeout(() => this.refresh(), 0);
            }
        },
        unregister(id) {
            this.references[id] = Math.max(0, (this.references[id] || 1) - 1);
            if (!this.references[id]) {
                delete this.references[id];
                delete this.items[id];
            }
            if (!Object.keys(this.references).length) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },
        async refresh() {
            if (this.busy || document.hidden || !Object.keys(this.references).length) return;
            this.busy = true;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 8000);
            try {
                const ids = Object.keys(this.references);
                for (let start = 0; start < ids.length; start += 50) {
                    const batch = ids.slice(start, start + 50);
                    const url = new URL(this.url, window.location.origin);
                    batch.forEach((id) => url.searchParams.append('ids[]', id));
                    const response = await fetch(url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    });
                    if (!response.ok) throw new Error('Availability refresh failed');
                    const data = await response.json();
                    for (const id of batch) {
                        if (!this.references[id]) continue;
                        const next = data.listings[id] || { unavailable: true };
                        if (JSON.stringify(this.items[id]) !== JSON.stringify(next)) this.items[id] = next;
                    }
                }
                this.stale = false;
            } catch {
                this.stale = true;
            } finally {
                clearTimeout(timeout);
                this.busy = false;
            }
        },
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) Alpine.store('listingAvailability').refresh();
    });
    window.addEventListener('online', () => Alpine.store('listingAvailability').refresh());

    Alpine.data('slotAvailability', () => ({
        id: '',
        init() {
            this.id = this.$el.dataset.listingId;
            this.$store.listingAvailability.register(this.id, JSON.parse(this.$el.dataset.initial), this.$el.dataset.url);
        },
        get label() {
            const item = this.$store.listingAvailability.items[this.id];
            return item?.unavailable ? this.$el.dataset.unavailableLabel : (item?.label || '');
        },
        destroy() {
            this.$store.listingAvailability.unregister(this.id);
        },
    }));
}
