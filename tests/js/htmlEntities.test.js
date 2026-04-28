// Load the functions.js file and extract only the htmlEntities function
const fs = require('fs');
const path = require('path');
const functionsJs = fs.readFileSync(
    path.join(__dirname, '../../public/js/functions.js'),
    'utf8'
);

// Extract just the htmlEntities function to avoid jQuery dependencies
const htmlEntitiesFunctionMatch = functionsJs.match(
    /function htmlEntities\(str\) \{[\s\S]*?\n\}/
);
if (htmlEntitiesFunctionMatch) {
    eval(htmlEntitiesFunctionMatch[0]);
}

describe('htmlEntities', function () {
    it('should handle null input', function () {
        expect(htmlEntities(null)).toBe('');
    });

    it('should handle undefined input', function () {
        expect(htmlEntities(undefined)).toBe('');
    });

    it('should handle empty string', function () {
        expect(htmlEntities('')).toBe('');
    });

    it('should handle strings without HTML entities', function () {
        expect(htmlEntities('Hello World')).toBe('Hello World');
        expect(htmlEntities('123 abc XYZ')).toBe('123 abc XYZ');
    });

    it('should escape ampersand', function () {
        expect(htmlEntities('Tom & Jerry')).toBe('Tom &amp; Jerry');
        expect(htmlEntities('&')).toBe('&amp;');
        expect(htmlEntities('A&B&C')).toBe('A&amp;B&amp;C');
    });

    it('should escape less than', function () {
        expect(htmlEntities('5 < 10')).toBe('5 &lt; 10');
        expect(htmlEntities('<')).toBe('&lt;');
        expect(htmlEntities('<script>')).toBe('&lt;script&gt;');
    });

    it('should escape greater than', function () {
        expect(htmlEntities('10 > 5')).toBe('10 &gt; 5');
        expect(htmlEntities('>')).toBe('&gt;');
        expect(htmlEntities('</script>')).toBe('&lt;/script&gt;');
    });

    it('should escape double quotes', function () {
        expect(htmlEntities('He said "Hello"')).toBe(
            'He said &quot;Hello&quot;'
        );
        expect(htmlEntities('"')).toBe('&quot;');
        expect(htmlEntities('title="value"')).toBe('title=&quot;value&quot;');
    });

    it('should escape single quotes', function () {
        expect(htmlEntities("It's working")).toBe('It&#39;s working');
        expect(htmlEntities("'")).toBe('&#39;');
        expect(htmlEntities("onclick='alert()'")).toBe(
            'onclick=&#39;alert()&#39;'
        );
    });

    it('should escape multiple different entities', function () {
        expect(htmlEntities('<div title="test">Tom & Jerry\'s</div>')).toBe(
            '&lt;div title=&quot;test&quot;&gt;Tom &amp; Jerry&#39;s&lt;/div&gt;'
        );
    });

    it('should handle all entities in one string', function () {
        expect(htmlEntities('&<>"\'')).toBe('&amp;&lt;&gt;&quot;&#39;');
    });

    it('should handle numbers and convert to string', function () {
        expect(htmlEntities(123)).toBe('123');
        expect(htmlEntities(0)).toBe('0');
    });

    it('should handle boolean values', function () {
        expect(htmlEntities(true)).toBe('true');
        expect(htmlEntities(false)).toBe('false');
    });

    it('should handle XSS prevention', function () {
        expect(htmlEntities('<script>alert("XSS")</script>')).toBe(
            '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;'
        );
        expect(htmlEntities('<img src="x" onerror="alert(\'XSS\')">')).toBe(
            '&lt;img src=&quot;x&quot; onerror=&quot;alert(&#39;XSS&#39;)&quot;&gt;'
        );
    });

    it('should handle mixed content', function () {
        expect(htmlEntities('Price: $5 < $10 & "discount" available')).toBe(
            'Price: $5 &lt; $10 &amp; &quot;discount&quot; available'
        );
    });
});
