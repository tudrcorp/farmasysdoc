Alpine.data('farmaadminDateRangePicker', (config = {}) => ({
    open: false,
    today: config.today,
    maxDays: Number(config.maxDays) || 366,
    draftStart: null,
    draftEnd: null,
    hoverDate: null,
    viewYear: 0,
    viewMonth: 0,
    monthsToShow: 2,
    popoverStyle: '',
    weekdays: ['lu', 'ma', 'mi', 'ju', 'vi', 'sá', 'do'],

    get appliedFrom() {
        return this.$wire.dashboardDateFrom;
    },

    get appliedTo() {
        return this.$wire.dashboardDateTo;
    },

    get selecting() {
        return this.draftStart !== null && this.draftEnd === null;
    },

    get canGoNext() {
        let today = this.parse(this.today);
        let lastVisibleIndex = this.viewYear * 12 + this.viewMonth + this.monthsToShow - 1;

        return lastVisibleIndex < today.getFullYear() * 12 + today.getMonth();
    },

    get months() {
        let months = [];

        for (let offset = 0; offset < this.monthsToShow; offset++) {
            months.push(this.buildMonth(new Date(this.viewYear, this.viewMonth + offset, 1)));
        }

        return months;
    },

    get draftLabel() {
        if (this.selecting) {
            return `${this.formatDay(this.draftStart)} – …`;
        }

        if (this.draftStart && this.draftEnd) {
            return this.formatRange(this.draftStart, this.draftEnd);
        }

        return '';
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        this.monthsToShow = window.matchMedia('(min-width: 40rem)').matches ? 2 : 1;
        this.draftStart = this.appliedFrom || null;
        this.draftEnd = this.appliedTo || null;
        this.hoverDate = null;

        let anchor = this.parse(this.appliedTo || this.today);
        let firstVisible = new Date(anchor.getFullYear(), anchor.getMonth() - (this.monthsToShow - 1), 1);

        this.viewYear = firstVisible.getFullYear();
        this.viewMonth = firstVisible.getMonth();
        this.open = true;

        this.$nextTick(() => requestAnimationFrame(() => this.reposition()));
    },

    close() {
        this.open = false;
        this.hoverDate = null;
    },

    closeFromOutside(event) {
        if (this.$refs.trigger?.contains(event.target)) {
            return;
        }

        this.close();
    },

    reposition() {
        if (! this.open || ! this.$refs.trigger || ! this.$refs.popover) {
            return;
        }

        let gutter = 16;
        let trigger = this.$refs.trigger.getBoundingClientRect();
        let width = this.$refs.popover.offsetWidth;
        let height = this.$refs.popover.offsetHeight;
        let left = Math.min(trigger.right - width, window.innerWidth - width - gutter);
        let top = trigger.bottom + 8;

        if (top + height > window.innerHeight - gutter && trigger.top - 8 - height > gutter) {
            top = trigger.top - 8 - height;
        }

        this.popoverStyle = `top: ${Math.round(top)}px; left: ${Math.round(Math.max(gutter, left))}px;`;
    },

    shiftMonths(step) {
        if (step > 0 && ! this.canGoNext) {
            return;
        }

        let firstVisible = new Date(this.viewYear, this.viewMonth + step, 1);

        this.viewYear = firstVisible.getFullYear();
        this.viewMonth = firstVisible.getMonth();
    },

    buildMonth(firstDay) {
        let monthName = new Intl.DateTimeFormat('es', { month: 'long' }).format(firstDay);
        let leadingBlanks = (firstDay.getDay() + 6) % 7;
        let daysInMonth = new Date(firstDay.getFullYear(), firstDay.getMonth() + 1, 0).getDate();
        let monthKey = this.key(firstDay).slice(0, 7);
        let cells = [];

        for (let blank = 0; blank < leadingBlanks; blank++) {
            cells.push({ key: `${monthKey}-blank-${blank}`, date: null, day: '', ariaLabel: null });
        }

        for (let day = 1; day <= daysInMonth; day++) {
            let date = new Date(firstDay.getFullYear(), firstDay.getMonth(), day);
            let dateKey = this.key(date);

            cells.push({
                key: dateKey,
                date: dateKey,
                day,
                ariaLabel: new Intl.DateTimeFormat('es', { dateStyle: 'full' }).format(date),
            });
        }

        return {
            key: monthKey,
            label: `${monthName.charAt(0).toUpperCase()}${monthName.slice(1)} ${firstDay.getFullYear()}`,
            cells,
        };
    },

    isDisabled(date) {
        if (date > this.today) {
            return true;
        }

        return this.selecting && Math.abs(this.diffDays(this.draftStart, date)) >= this.maxDays;
    },

    visibleRange() {
        if (this.selecting) {
            let edge = this.hoverDate && ! this.isDisabled(this.hoverDate) ? this.hoverDate : this.draftStart;

            return edge < this.draftStart ? [edge, this.draftStart] : [this.draftStart, edge];
        }

        return [this.draftStart, this.draftEnd];
    },

    dayClasses(cell) {
        if (cell.date === null) {
            return 'fi-farmaadmin-date-range__day--empty';
        }

        let [start, end] = this.visibleRange();
        let isStart = start !== null && cell.date === start;
        let isEnd = end !== null && cell.date === end;

        return {
            'fi-farmaadmin-date-range__day--start': isStart,
            'fi-farmaadmin-date-range__day--end': isEnd,
            'fi-farmaadmin-date-range__day--single': isStart && isEnd,
            'fi-farmaadmin-date-range__day--in-range': start !== null && end !== null && cell.date > start && cell.date < end,
            'fi-farmaadmin-date-range__day--preview': this.selecting,
            'fi-farmaadmin-date-range__day--today': cell.date === this.today,
        };
    },

    selectDay(date) {
        if (date === null || this.isDisabled(date)) {
            return;
        }

        if (! this.selecting) {
            this.draftStart = date;
            this.draftEnd = null;

            return;
        }

        let [from, to] = date < this.draftStart ? [date, this.draftStart] : [this.draftStart, date];

        this.applyRange(from, to);
    },

    resetToMonth() {
        this.applyRange(`${this.today.slice(0, 7)}-01`, this.today);
    },

    isApplied(from, to) {
        return from === this.appliedFrom && to === this.appliedTo;
    },

    applyRange(from, to) {
        this.draftStart = from;
        this.draftEnd = to;
        this.close();

        if (this.isApplied(from, to)) {
            return;
        }

        this.$root.dispatchEvent(new CustomEvent('farmaadmin-dashboard-range-selected', {
            bubbles: true,
            detail: { label: this.formatRange(from, to) },
        }));

        this.$wire.applyDashboardDateRange(from, to);
    },

    parse(dateKey) {
        let [year, month, day] = String(dateKey).split('-').map(Number);

        return new Date(year, month - 1, day);
    },

    key(date) {
        let month = String(date.getMonth() + 1).padStart(2, '0');
        let day = String(date.getDate()).padStart(2, '0');

        return `${date.getFullYear()}-${month}-${day}`;
    },

    diffDays(from, to) {
        return Math.round((this.parse(to) - this.parse(from)) / 86400000);
    },

    shortMonth(date) {
        return new Intl.DateTimeFormat('es', { month: 'short' }).format(date).replace('.', '');
    },

    formatDay(dateKey) {
        let date = this.parse(dateKey);

        return `${date.getDate()} ${this.shortMonth(date)} ${date.getFullYear()}`;
    },

    formatRange(from, to) {
        let start = this.parse(from);
        let end = this.parse(to);

        if (from === to) {
            return this.formatDay(from);
        }

        if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
            return `${start.getDate()} – ${end.getDate()} ${this.shortMonth(end)} ${end.getFullYear()}`;
        }

        if (start.getFullYear() === end.getFullYear()) {
            return `${start.getDate()} ${this.shortMonth(start)} – ${end.getDate()} ${this.shortMonth(end)} ${end.getFullYear()}`;
        }

        return `${this.formatDay(from)} – ${this.formatDay(to)}`;
    },
}));
