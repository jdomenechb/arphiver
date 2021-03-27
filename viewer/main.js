$(() => {
    let preview = $('#preview');

    $('#update').click((e) => {
        preview.empty();

        let source = JSON.parse($('#source').val());
        let template = $('#template').val();
        let fields = [...template.matchAll(/\{\{\s*([^\s\}]+)\s*\}\}/g)];

        source.forEach((item) => {
            let result = template;

            fields.forEach((element) => {
                let queryResult = jsonpath.query(item, element[1]);
                result = result.replace(element[0], queryResult[0]);
            })

            preview.append(result + '<hr/>');
        });
    });
});