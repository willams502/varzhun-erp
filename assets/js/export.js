/**
 * Exportação real para PDF (jsPDF) e Excel (SheetJS) + Comprovantes/Orçamentos em PDF.
 */
const Exporter = {
    toExcel(filename, sheets) {
        if (!window.XLSX) return showToast('Biblioteca Excel não carregada.', 'error');
        const wb = XLSX.utils.book_new();
        sheets.forEach(({ name, rows }) => {
            const ws = XLSX.utils.json_to_sheet(rows);
            XLSX.utils.book_append_sheet(wb, ws, name.slice(0, 31));
        });
        XLSX.writeFile(wb, `${filename}.xlsx`);
        showToast('Planilha Excel exportada!');
    },

    toPDF(filename, title, columns, rows, summaryLines = []) {
        if (!window.jspdf) return showToast('Biblioteca PDF não carregada.', 'error');
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        doc.setFontSize(16);
        doc.text('Varzhun Impressão 3D — ' + title, 14, 18);
        doc.setFontSize(10);
        doc.text('Gerado em: ' + new Date().toLocaleString('pt-BR'), 14, 25);

        let y = 32;
        summaryLines.forEach(line => {
            doc.text(line, 14, y);
            y += 6;
        });

        doc.autoTable({
            startY: y + 2,
            head: [columns],
            body: rows,
            styles: { fontSize: 9 },
            headStyles: { fillColor: [79, 70, 229] }
        });
        doc.save(`${filename}.pdf`);
        showToast('Arquivo PDF gerado!');
    },

    salePDF(sale) {
        if (!window.jspdf) return showToast('Biblioteca PDF não carregada.', 'error');
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        const isQuote = sale.sale_type === 'orcamento';
        doc.setFontSize(18);
        doc.text('Varzhun Impressão 3D Ltda', 14, 18);
        doc.setFontSize(12);
        doc.text((isQuote ? 'ORÇAMENTO: ' : 'COMPROVANTE DE VENDA: ') + sale.code, 14, 27);
        doc.setFontSize(10);
        doc.text(`Cliente: ${sale.customer_name || '—'}   |   CPF/CNPJ: ${sale.cpf_cnpj || '—'}`, 14, 35);
        doc.text(`Data: ${fmtDate(sale.sale_date)}   |   Vencimento: ${fmtDate(sale.due_date)}   |   Pagamento: ${sale.payment_method}`, 14, 41);

        const body = (sale.items || []).map(i => [
            i.description,
            String(i.quantity),
            fmtBRL(i.unit_price),
            fmtBRL(i.total_price)
        ]);

        doc.autoTable({
            startY: 48,
            head: [['Produto / Serviço', 'Qtd', 'Unitário', 'Total']],
            body,
            headStyles: { fillColor: [79, 70, 229] }
        });

        const finalY = doc.lastAutoTable.finalY + 10;
        doc.setFontSize(11);
        doc.text(`Subtotal: ${fmtBRL(sale.subtotal)}`, 14, finalY);
        doc.text(`Desconto: ${fmtBRL(sale.discount_value)}`, 14, finalY + 6);
        doc.setFontSize(13);
        doc.text(`Total: ${fmtBRL(sale.total_amount)}`, 14, finalY + 14);
        if (!isQuote) {
            doc.setFontSize(10);
            doc.text(`Pago: ${fmtBRL(sale.paid_amount)}   |   Saldo Restante: ${fmtBRL(Math.max(0, sale.total_amount - sale.paid_amount))}`, 14, finalY + 22);
        }
        doc.save(`${sale.code}.pdf`);
        showToast('PDF gerado com sucesso!');
    }
};
