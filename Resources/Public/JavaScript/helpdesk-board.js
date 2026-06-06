import DocumentService from '@typo3/core/document-service.js';

class HelpdeskBoard {
  constructor() {
    this.draggedCard = null;
    this.board = null;
    this.statusForm = null;
    DocumentService.ready().then(() => this.initialize());
  }

  initialize() {
    this.board = document.querySelector('[data-helpdesk-board]');
    this.statusForm = document.querySelector('[data-helpdesk-board-status-form]');
    if (!this.board || !this.statusForm) {
      return;
    }

    this.registerCards();
    this.registerColumns();
  }

  registerCards() {
    this.board.querySelectorAll('[data-helpdesk-ticket-card]').forEach((card) => {
      card.addEventListener('dragstart', (event) => this.onDragStart(event, card));
      card.addEventListener('dragend', () => this.onDragEnd());
    });
  }

  registerColumns() {
    this.board.querySelectorAll('[data-helpdesk-board-column]').forEach((column) => {
      column.addEventListener('dragover', (event) => this.onDragOver(event, column));
      column.addEventListener('dragenter', (event) => this.onDragEnter(event, column));
      column.addEventListener('dragleave', (event) => this.onDragLeave(event, column));
      column.addEventListener('drop', (event) => this.onDrop(event, column));
    });
  }

  onDragStart(event, card) {
    this.draggedCard = card;
    card.classList.add('helpdesk-ticket-card-dragging');
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', card.dataset.ticketUid || '');
  }

  onDragEnd() {
    if (this.draggedCard) {
      this.draggedCard.classList.remove('helpdesk-ticket-card-dragging');
    }
    this.board.querySelectorAll('[data-helpdesk-board-column]').forEach((column) => {
      column.classList.remove('helpdesk-board-column-drop-target');
    });
    this.draggedCard = null;
  }

  onDragOver(event, column) {
    if (!this.canDrop(column)) {
      return;
    }
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
  }

  onDragEnter(event, column) {
    if (!this.canDrop(column)) {
      return;
    }
    event.preventDefault();
    column.classList.add('helpdesk-board-column-drop-target');
  }

  onDragLeave(event, column) {
    if (event.relatedTarget && column.contains(event.relatedTarget)) {
      return;
    }
    column.classList.remove('helpdesk-board-column-drop-target');
  }

  onDrop(event, column) {
    if (!this.canDrop(column)) {
      return;
    }
    event.preventDefault();
    column.classList.remove('helpdesk-board-column-drop-target');

    const ticketUid = parseInt(this.draggedCard.dataset.ticketUid || '0', 10);
    const currentStatusUid = parseInt(this.draggedCard.dataset.statusUid || '0', 10);
    const targetStatusUid = parseInt(column.dataset.statusUid || '0', 10);

    if (!ticketUid || !targetStatusUid || currentStatusUid === targetStatusUid) {
      return;
    }

    this.submitStatusChange(ticketUid, targetStatusUid);
  }

  canDrop(column) {
    return !!this.draggedCard && column.dataset.statusUid !== this.draggedCard.dataset.statusUid;
  }

  submitStatusChange(ticketUid, statusUid) {
    const ticketField = this.statusForm.querySelector('[name="ticket"]');
    const statusField = this.statusForm.querySelector('[name="status"]');
    if (!ticketField || !statusField) {
      return;
    }

    ticketField.value = String(ticketUid);
    statusField.value = String(statusUid);
    this.statusForm.submit();
  }
}

export default new HelpdeskBoard();
