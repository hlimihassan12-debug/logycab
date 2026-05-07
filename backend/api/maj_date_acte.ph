function majDateActe(id, val) {
    fetch('backend/api/maj_date_acte.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, date: val})
    }).then(r => r.json()).then(d => {
        if (d.ok) console.log('Date acte mise à jour');
    });
}
function majDateFacture(id, val) {
    fetch('backend/api/maj_date_facture.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, date: val})
    }).then(r => r.json()).then(d => {
        if (d.ok) console.log('Date facture mise à jour');
    });
}