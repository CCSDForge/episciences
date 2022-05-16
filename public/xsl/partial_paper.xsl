<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
                xmlns:php="http://php.net/xsl"
                xmlns:dc="http://purl.org/dc/elements/1.1/"
                xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/">

    <xsl:output method="html" encoding="utf-8" indent="yes"/>

    <xsl:template match="/record">

        <xsl:variable name="client_language" select="php:function('Episciences_Tools::getLocale')"/>
        <xsl:variable name="doc_language" select="metadata/oai_dc:dc/dc:language"/>
        <xsl:variable name="title">
            <xsl:choose>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $client_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $client_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang = $doc_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang = $doc_language]"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:title/@xml:lang">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title[@xml:lang]"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="metadata/oai_dc:dc/dc:title"/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="description">
            <xsl:choose>
                <xsl:when test="metadata/oai_dc:dc/dc:description/@xml:lang = $client_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description[@xml:lang = $client_language]"
                                  disable-output-escaping="yes"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:description/@xml:lang = $doc_language">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description[@xml:lang = $doc_language]"
                                  disable-output-escaping="yes"/>
                </xsl:when>
                <xsl:when test="metadata/oai_dc:dc/dc:description/@xml:lang">
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description[@xml:lang]"
                                  disable-output-escaping="yes"/>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:value-of select="metadata/oai_dc:dc/dc:description" disable-output-escaping="yes"/>
                </xsl:otherwise>
            </xsl:choose>
        </xsl:variable>
        <xsl:variable name="docUrl" select="episciences/docURL"/>
        <xsl:variable name="docId" select="episciences/id"/>

        <div class="panel panel-default collapsable" style="margin-top: 20px">

            <div class="panel-heading">
                <h2 class="panel-title">

                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($title))"/>
                    (v<xsl:value-of select="episciences/version"/>)
                </h2>
            </div>

            <div class="panel-body in">

                <p>
                    <i>
                        <div>
                            <xsl:value-of select="episciences/authorEnriched" disable-output-escaping="yes"/>
                        </div>

                        <xsl:if test="episciences/submission_date">
                            <div>
                                <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Proposé par : ')"/>
                                <xsl:value-of select="episciences/submitter"/> (<xsl:value-of
                                    select="php:function('Episciences_View_Helper_Date::Date', string(episciences/submission_date))"/>)
                            </div>
                        </xsl:if>
                    </i>
                </p>

                <p class="small" style="text-align: justify">
                    <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($description))"/>
                </p>

                <hr/>

                <xsl:if test="episciences/id and episciences/id != 0">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Id : ')"/>
                        <xsl:value-of select="episciences/id"/>
                    </div>
                </xsl:if>

                <xsl:if test="episciences/paperLicence/text() != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                        <a rel="noopener" target="_blank">
                            <xsl:attribute name="href">
                                <xsl:value-of select="episciences/paperLicence/text()"/>
                            </xsl:attribute>
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', string(episciences/paperLicence))"/>
                        </a>
                    </div>
                </xsl:if>

                <div class="small">
                    <xsl:choose>
                        <xsl:when test="episciences/tmp/text() = '1'">
                            <xsl:variable name="docUrls" select="php:function('Episciences_Tools::buildHtmlTmpDocUrls', $docId)"/>
                            <xsl:value-of select=" $docUrls" disable-output-escaping="yes"/>
                        </xsl:when>
                        <xsl:otherwise>
                            <a target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="$docUrl"/>
                                </xsl:attribute>
                                <xsl:value-of select="$docUrl"/>
                            </a>
                        </xsl:otherwise>
                    </xsl:choose>
                </div>

                <xsl:if test="episciences/notHasHook/text() = '1' and (episciences/docURL != episciences/paperURL)">
                    <div class="small">
                        <a target="_blank">
                            <xsl:attribute name="href">
                                <xsl:value-of select="episciences/paperURL"/>
                            </xsl:attribute>
                            <xsl:value-of select="episciences/paperURL"/>
                        </a>
                    </div>
                </xsl:if>

            </div>

        </div>

    </xsl:template>

</xsl:stylesheet> 