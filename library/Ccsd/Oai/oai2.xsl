<?xml version="1.0" encoding="utf-8"?>
<!--
  XSL Transform to convert OAI 2.0 responses into XHTML
  Modernized for Episciences
-->
<xsl:stylesheet
    version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
    xmlns:oai="http://www.openarchives.org/OAI/2.0/"
>

<xsl:output method="html" encoding="UTF-8" indent="yes" />

<xsl:template name="style">
:root {
    --primary-color: #2c3e50;
    --secondary-color: #34495e;
    --accent-color: #3498db;
    --bg-color: #f8f9fa;
    --card-bg: #ffffff;
    --border-color: #dee2e6;
    --text-color: #212529;
    --key-bg: #e9ecef;
    --link-color: #0056b3;
    --error-bg: #f8d7da;
    --error-text: #721c24;
}

body { 
    margin: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    line-height: 1.5;
    color: var(--text-color);
    background-color: var(--bg-color);
    padding: 2rem;
}

.container {
    max-width: 1000px;
    margin: 0 auto;
}

header {
    margin-bottom: 2rem;
    border-bottom: 3px solid var(--accent-color);
    padding-bottom: 1rem;
}

h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 2rem;
}

h2 {
    color: var(--secondary-color);
    margin-top: 2rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
}

h3 {
    font-size: 1.2rem;
    margin-top: 1.5rem;
    color: var(--secondary-color);
}

.intro {
    background: #e7f3fe;
    padding: 1rem;
    border-radius: 4px;
    border-left: 5px solid var(--accent-color);
    margin-bottom: 2rem;
    font-size: 0.9rem;
}

nav {
    margin: 1rem 0;
}

ul.quicklinks {
    list-style: none;
    padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    background: var(--card-bg);
    padding: 1rem;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

ul.quicklinks li a {
    text-decoration: none;
    color: var(--link-color);
    font-weight: 600;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    transition: background 0.2s;
}

ul.quicklinks li a:hover {
    background: #e9ecef;
}

table.values {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

table.values td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid var(--border-color);
}

table.values td.key {
    width: 30%;
    background-color: var(--key-bg);
    font-weight: bold;
    color: var(--primary-color);
}

.results {
    margin-bottom: 2rem;
}

.oaiRecord {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    margin-bottom: 2rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.oaiRecordTitle {
    background: var(--secondary-color);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px 8px 0 0;
    margin: -1.5rem -1.5rem 1.5rem -1.5rem;
    font-size: 1.1rem;
}

.btn-link {
    display: inline-block;
    background: var(--accent-color);
    color: white !important;
    padding: 0.25rem 0.75rem;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.85rem;
    margin-left: 0.5rem;
    transition: opacity 0.2s;
}

.btn-link:hover {
    opacity: 0.9;
}

.error-box {
    background-color: var(--error-bg);
    color: var(--error-text);
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
    border: 1px solid #f5c6cb;
}

.metadata {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 2px dashed var(--border-color);
}

.dcdata h3 {
    margin-top: 0;
}

.xmlSource {
    font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 0.85rem;
    background: #272822;
    color: #f8f8f2;
    padding: 1.5rem;
    border-radius: 8px;
    overflow-x: auto;
}

.xmlBlock { padding-left: 1.5rem; }
.xmlTagName { color: #f92672; font-weight: bold; }
.xmlAttrName { color: #a6e22e; }
.xmlAttrValue { color: #e6db74; }
.xmlText { color: #f8f8f2; }

footer {
    margin-top: 4rem;
    padding: 2rem 0;
    border-top: 1px solid var(--border-color);
    font-size: 0.8rem;
    text-align: center;
    color: #6c757d;
}

@media (max-width: 600px) {
    body { padding: 1rem; }
    table.values td { display: block; width: auto !important; }
    table.values td.key { border-bottom: none; padding-bottom: 0; }
    ul.quicklinks { flex-direction: column; }
}
</xsl:template>

<xsl:variable name='identifier' select="substring-before(concat(substring-after(/oai:OAI-PMH/oai:request,'identifier='),'&amp;'),'&amp;')" />

<xsl:template match="/">
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OAI-PMH 2.0 Repository Explorer</title>
    <style><xsl:call-template name="style"/></style>
  </head>
  <body>
    <div class="container">
        <header role="banner">
            <h1>OAI-PMH 2.0 Repository Explorer</h1>
        </header>
        
        <nav role="navigation" aria-label="OAI verbs">
            <xsl:call-template name="quicklinks"/>
        </nav>

        <main role="main">
            <div class="intro">
                <strong>Information:</strong> You are viewing an HTML representation of an XML OAI-PMH response. 
                This interface is intended for human debugging and exploration. 
                Harvesting tools should use the raw XML.
            </div>

            <xsl:apply-templates select="/oai:OAI-PMH" />
        </main>

        <footer role="contentinfo">
            <nav aria-label="Footer navigation">
                <xsl:call-template name="quicklinks"/>
            </nav>
            <p id="moreinfo">
                Episciences OAI-PMH interface. 
                Based on original XSL by Christopher Gutteridge (University of Southampton).
            </p>
        </footer>
    </div>
  </body>
</html>
</xsl:template>

<xsl:template name="quicklinks">
    <ul class="quicklinks">
      <li><a href="?verb=Identify">Identify</a></li> 
      <li><a href="?verb=ListRecords&amp;metadataPrefix=oai_dc">ListRecords</a></li>
      <li><a href="?verb=ListSets">ListSets</a></li>
      <li><a href="?verb=ListMetadataFormats">ListMetadataFormats</a></li>
      <li><a href="?verb=ListIdentifiers&amp;metadataPrefix=oai_dc">ListIdentifiers</a></li>
    </ul>
</xsl:template>


<xsl:template match="/oai:OAI-PMH">
  <section class="response-header">
      <h2>General Information</h2>
      <table class="values">
        <tr><td class="key">Response Date</td>
        <td class="value"><xsl:value-of select="oai:responseDate"/></td></tr>
        <tr><td class="key">Request URL</td>
        <td class="value"><code><xsl:value-of select="oai:request"/></code></td></tr>
      </table>
  </section>

  <xsl:choose>
    <xsl:when test="oai:error">
      <section class="errors">
          <h2>OAI Errors</h2>
          <p>The request could not be completed due to the following error(s):</p>
          <div class="results">
            <xsl:apply-templates select="oai:error"/>
          </div>
      </section>
    </xsl:when>
    <xsl:otherwise>
      <section class="verb-results">
          <h2>Results for verb: <code><xsl:value-of select="oai:request/@verb"/></code></h2>
          <div class="results">
            <xsl:apply-templates select="oai:Identify" />
            <xsl:apply-templates select="oai:GetRecord"/>
            <xsl:apply-templates select="oai:ListRecords"/>
            <xsl:apply-templates select="oai:ListSets"/>
            <xsl:apply-templates select="oai:ListMetadataFormats"/>
            <xsl:apply-templates select="oai:ListIdentifiers"/>
          </div>
      </section>
    </xsl:otherwise>
  </xsl:choose>
</xsl:template>


<!-- ERROR -->

<xsl:template match="/oai:OAI-PMH/oai:error">
  <div class="error-box">
    <strong>Error Code:</strong> <code><xsl:value-of select="@code"/></code>
    <p><xsl:value-of select="." /></p>
  </div>
</xsl:template>

<!-- IDENTIFY -->

<xsl:template match="/oai:OAI-PMH/oai:Identify">
  <table class="values">
    <tr><td class="key">Repository Name</td>
    <td class="value"><xsl:value-of select="oai:repositoryName"/></td></tr>
    <tr><td class="key">Base URL</td>
    <td class="value"><xsl:value-of select="oai:baseURL"/></td></tr>
    <tr><td class="key">Protocol Version</td>
    <td class="value"><xsl:value-of select="oai:protocolVersion"/></td></tr>
    <tr><td class="key">Earliest Datestamp</td>
    <td class="value"><xsl:value-of select="oai:earliestDatestamp"/></td></tr>
    <tr><td class="key">Deleted Record Policy</td>
    <td class="value"><xsl:value-of select="oai:deletedRecord"/></td></tr>
    <tr><td class="key">Granularity</td>
    <td class="value"><xsl:value-of select="oai:granularity"/></td></tr>
    <xsl:apply-templates select="oai:adminEmail"/>
  </table>
  <xsl:apply-templates select="oai:description"/>
</xsl:template>

<xsl:template match="/oai:OAI-PMH/oai:Identify/oai:adminEmail">
    <tr><td class="key">Admin Email</td>
    <td class="value"><a href="mailto:{.}"><xsl:value-of select="."/></a></td></tr>
</xsl:template>

<!--
   Identify / EPrints
-->

<xsl:template match="ep:eprints" xmlns:ep="http://www.openarchives.org/OAI/1.1/eprints">
  <div class="oaiRecord">
      <h3 class="oaiRecordTitle">EPrints Description</h3>
      <xsl:if test="ep:content">
        <h4>Content</h4>
        <xsl:apply-templates select="ep:content"/>
      </xsl:if>
      <xsl:if test="ep:submissionPolicy">
        <h4>Submission Policy</h4>
        <xsl:apply-templates select="ep:submissionPolicy"/>
      </xsl:if>
      <h4>Metadata Policy</h4>
      <xsl:apply-templates select="ep:metadataPolicy"/>
      <h4>Data Policy</h4>
      <xsl:apply-templates select="ep:dataPolicy"/>
      <xsl:apply-templates select="ep:comment"/>
  </div>
</xsl:template>

<xsl:template match="ep:content|ep:dataPolicy|ep:metadataPolicy|ep:submissionPolicy" xmlns:ep="http://www.openarchives.org/OAI/1.1/eprints">
  <div style="margin-bottom: 1rem;">
      <xsl:if test="ep:text">
        <p><xsl:value-of select="ep:text" /></p>
      </xsl:if>
      <xsl:if test="ep:URL">
        <div><a href="{ep:URL}"><xsl:value-of select="ep:URL" /></a></div>
      </xsl:if>
  </div>
</xsl:template>

<xsl:template match="ep:comment" xmlns:ep="http://www.openarchives.org/OAI/1.1/eprints">
  <h4>Comment</h4>
  <p><xsl:value-of select="."/></p>
</xsl:template>

<!--
   Identify / Unsupported Description
-->

<xsl:template match="oai:description/*" priority="-100">
  <h3>Unsupported Description Type</h3>
  <div class="xmlSource">
    <xsl:apply-templates select="." mode='xmlMarkup' />
  </div>
</xsl:template>


<!--
   Identify / OAI-Identifier
-->

<xsl:template match="id:oai-identifier" xmlns:id="http://www.openarchives.org/OAI/2.0/oai-identifier">
  <h3>OAI-Identifier</h3>
  <table class="values">
    <tr><td class="key">Scheme</td>
    <td class="value"><xsl:value-of select="id:scheme"/></td></tr>
    <tr><td class="key">Repository Identifier</td>
    <td class="value"><xsl:value-of select="id:repositoryIdentifier"/></td></tr>
    <tr><td class="key">Delimiter</td>
    <td class="value"><xsl:value-of select="id:delimiter"/></td></tr>
    <tr><td class="key">Sample OAI Identifier</td>
    <td class="value"><code><xsl:value-of select="id:sampleIdentifier"/></code></td></tr>
  </table>
</xsl:template>

<!-- Identify / Friends -->

<xsl:template match="fr:friends" xmlns:fr="http://www.openarchives.org/OAI/2.0/friends/">
  <h3>Friends</h3>
  <ul>
    <xsl:apply-templates select="fr:baseURL"/>
  </ul>
</xsl:template>

<xsl:template match="fr:baseURL" xmlns:fr="http://www.openarchives.org/OAI/2.0/friends/">
  <li><xsl:value-of select="."/> 
<xsl:text> </xsl:text>
<a class="btn-link" href="{.}?verb=Identify">Identify</a></li>
</xsl:template>


<!-- GetRecord -->

<xsl:template match="oai:GetRecord">
  <xsl:apply-templates select="oai:record" />
</xsl:template>

<!-- ListRecords -->

<xsl:template match="oai:ListRecords">
  <xsl:apply-templates select="oai:record" />
  <xsl:apply-templates select="oai:resumptionToken" />
</xsl:template>

<!-- ListIdentifiers -->

<xsl:template match="oai:ListIdentifiers">
  <xsl:apply-templates select="oai:header" />
  <xsl:apply-templates select="oai:resumptionToken" />
</xsl:template>

<!-- ListSets -->

<xsl:template match="oai:ListSets">
  <xsl:apply-templates select="oai:set" />
  <xsl:apply-templates select="oai:resumptionToken" />
</xsl:template>

<xsl:template match="oai:set">
  <div class="oaiRecord">
      <h3 class="oaiRecordTitle">Set: <xsl:value-of select="oai:setName"/></h3>
      <table class="values">
        <tr><td class="key">setSpec</td>
        <td class="value"><code><xsl:value-of select="oai:setSpec"/></code></td></tr>
      </table>
  </div>
</xsl:template>

<!-- ListMetadataFormats -->

<xsl:template match="oai:ListMetadataFormats">
  <xsl:choose>
    <xsl:when test="$identifier">
      <p>Available metadata formats for record <code><xsl:value-of select='$identifier' /></code>:</p>
    </xsl:when>
    <xsl:otherwise>
      <p>Available metadata formats from this repository:</p>
    </xsl:otherwise>
  </xsl:choose>
  <div class="formats-list">
    <xsl:apply-templates select="oai:metadataFormat" />
  </div>
</xsl:template>

<xsl:template match="oai:metadataFormat">
  <div class="oaiRecord">
      <h3 class="oaiRecordTitle">Format: <xsl:value-of select="oai:metadataPrefix"/></h3>
      <table class="values">
        <tr><td class="key">Prefix</td>
        <td class="value">
            <strong><xsl:value-of select="oai:metadataPrefix"/></strong>
            <a class="btn-link" href="?verb=ListRecords&amp;metadataPrefix={oai:metadataPrefix}">List Records</a>
        </td></tr>
        <tr><td class="key">Namespace</td>
        <td class="value"><code><xsl:value-of select="oai:metadataNamespace"/></code></td></tr>
        <tr><td class="key">Schema</td>
        <td class="value"><a href="{oai:schema}"><xsl:value-of select="oai:schema"/></a></td></tr>
      </table>
  </div>
</xsl:template>

<!-- record object -->

<xsl:template match="oai:record">
  <div class="oaiRecord">
    <h3 class="oaiRecordTitle">OAI Record: <xsl:value-of select="oai:header/oai:identifier"/></h3>
    <xsl:apply-templates select="oai:header" />
    <xsl:apply-templates select="oai:metadata" />
    <xsl:apply-templates select="oai:about" />
  </div>
</xsl:template>

<xsl:template match="oai:header">
  <div class="record-header">
      <h4>Header</h4>
      <table class="values">
        <tr><td class="key">Identifier</td>
        <td class="value">
          <code><xsl:value-of select="oai:identifier"/></code>
          <a class="btn-link" href="?verb=GetRecord&amp;metadataPrefix=oai_dc&amp;identifier={oai:identifier}">oai_dc</a>
          <a class="btn-link" href="?verb=ListMetadataFormats&amp;identifier={oai:identifier}">formats</a>
        </td></tr>
        <tr><td class="key">Datestamp</td>
        <td class="value"><xsl:value-of select="oai:datestamp"/></td></tr>
      <xsl:apply-templates select="oai:setSpec" />
      </table>
      <xsl:if test="@status='deleted'">
        <div class="error-box"><strong>Status:</strong> This record has been deleted.</div>
      </xsl:if>
  </div>
</xsl:template>


<xsl:template match="oai:about">
  <div class="about-section">
      <h4>About</h4>
      <div class="xmlSource">
        <xsl:apply-templates select="*" mode="xmlMarkup" />
      </div>
  </div>
</xsl:template>

<xsl:template match="oai:metadata">
  <div class="metadata">
    <h4>Metadata Content</h4>
    <xsl:apply-templates select="*" />
  </div>
</xsl:template>

<!-- oai setSpec object -->

<xsl:template match="oai:setSpec">
  <tr><td class="key">setSpec</td>
  <td class="value">
    <code><xsl:value-of select="."/></code>
    <a class="btn-link" href="?verb=ListIdentifiers&amp;metadataPrefix=oai_dc&amp;set={.}">Identifiers</a>
    <a class="btn-link" href="?verb=ListRecords&amp;metadataPrefix=oai_dc&amp;set={.}">Records</a>
  </td></tr>
</xsl:template>

<!-- oai resumptionToken -->

<xsl:template match="oai:resumptionToken">
   <div class="resumption-token">
       <h3>Pagination</h3>
       <p>There are more results available.</p>
       <table class="values">
         <tr><td class="key">Token</td>
         <td class="value">
            <code><xsl:value-of select="."/></code>
            <span style="margin-left: 1rem; color: #666;">
                (<xsl:value-of select="@cursor"/> / <xsl:value-of select="@completeListSize"/>)
            </span>
            <a class="btn-link" href="?verb={/oai:OAI-PMH/oai:request/@verb}&amp;resumptionToken={.}">Resume harvesting</a>
         </td></tr>
       </table>
   </div>
</xsl:template>

<!-- unknown metadata format -->

<xsl:template match="oai:metadata/*" priority='-100'>
  <div class="xmlSource">
    <xsl:apply-templates select="." mode='xmlMarkup' />
  </div>
</xsl:template>

<!-- oai_dc record -->

<xsl:template match="oai_dc:dc"  xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" >
  <div class="dcdata">
    <table class="values">
      <xsl:apply-templates select="*" />
    </table>
  </div>
</xsl:template>

<xsl:template match="dc:title" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Title</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:creator" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Author / Creator</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:subject" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Subject</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:description" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Description</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:publisher" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Publisher</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:contributor" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Contributor</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:date" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Date</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:type" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Type</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:format" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Format</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:identifier" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Identifier</td><td class="value"><code><xsl:value-of select="."/></code></td></tr></xsl:template>

<xsl:template match="dc:source" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Source</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:language" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Language</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:relation" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Relation</td><td class="value">
  <xsl:choose>
    <xsl:when test='starts-with(.,"http" )'>
      <a href="{.}"><xsl:value-of select="."/></a>
    </xsl:when>
    <xsl:otherwise>
      <xsl:value-of select="."/>
    </xsl:otherwise>
  </xsl:choose>
</td></tr></xsl:template>

<xsl:template match="dc:coverage" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Coverage</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<xsl:template match="dc:rights" xmlns:dc="http://purl.org/dc/elements/1.1/">
<tr><td class="key">Rights</td><td class="value"><xsl:value-of select="."/></td></tr></xsl:template>

<!-- XML Pretty Maker -->

<xsl:template match="node()" mode='xmlMarkup'>
  <div class="xmlBlock">
    &lt;<span class="xmlTagName"><xsl:value-of select='name(.)' /></span><xsl:apply-templates select="@*" mode='xmlMarkup'/>&gt;<xsl:apply-templates select="node()" mode='xmlMarkup' />&lt;/<span class="xmlTagName"><xsl:value-of select='name(.)' /></span>&gt;
  </div>
</xsl:template>

<xsl:template match="text()" mode='xmlMarkup'><span class="xmlText"><xsl:value-of select='.' /></span></xsl:template>

<xsl:template match="@*" mode='xmlMarkup'>
  <xsl:text> </xsl:text><span class="xmlAttrName"><xsl:value-of select='name()' /></span>="<span class="xmlAttrValue"><xsl:value-of select='.' /></span>"
</xsl:template>

</xsl:stylesheet>
