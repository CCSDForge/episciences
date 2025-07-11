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
        <!-- Determine primary description for backward compatibility -->
        <xsl:variable name="primary_description">
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
                                <span class="label label-default pull-right"><xsl:value-of select="php:function('Ccsd_Tools::translate',string(episciences/submissionType))"/></span>
                            </div>
                        </xsl:if>
                    </i>
                </p>

                <!-- Display all available descriptions with language labels -->
                <xsl:choose>
                    <xsl:when test="count(metadata/oai_dc:dc/dc:description) > 1">
                        <!-- Multiple descriptions: show each with language label -->
                        <xsl:for-each select="metadata/oai_dc:dc/dc:description">
                            <div class="small force-word-wrap description-multilingual" style="text-align: justify; margin-bottom: 10px;">
                                <xsl:if test="@xml:lang">
                                    <strong class="text-muted">
                                        <xsl:text>[</xsl:text>
                                        <xsl:choose>
                                            <xsl:when test="@xml:lang = 'en'">English</xsl:when>
                                            <xsl:when test="@xml:lang = 'fr'">Français</xsl:when>
                                            <xsl:when test="@xml:lang = 'es'">Español</xsl:when>
                                            <xsl:when test="@xml:lang = 'de'">Deutsch</xsl:when>
                                            <xsl:when test="@xml:lang = 'it'">Italiano</xsl:when>
                                            <xsl:when test="@xml:lang = 'cpg'">Ελληνικά</xsl:when>
                                            <xsl:otherwise><xsl:value-of select="@xml:lang"/></xsl:otherwise>
                                        </xsl:choose>
                                        <xsl:text>] </xsl:text>
                                    </strong>
                                </xsl:if>
                                <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string(.))" disable-output-escaping="yes"/>
                            </div>
                        </xsl:for-each>
                    </xsl:when>
                    <xsl:otherwise>
                        <!-- Single description: show without language label -->
                        <p class="small force-word-wrap" style="text-align: justify">
                            <xsl:value-of select="php:function('Episciences_Tools::decodeLatex', string($primary_description))"/>
                        </p>
                    </xsl:otherwise>
                </xsl:choose>

                <hr/>

                <xsl:if test="episciences/id and episciences/id != ''">
                    <div class="small">
                        <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Id : ')"/>
                        <xsl:value-of select="episciences/id"/>
                    </div>
                </xsl:if>

                <!-- licenses -->

                <xsl:choose>
                    <xsl:when test="episciences/paperLicence/text() != ''">
                        <div class="small">
                            <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                            <a rel="noopener" target="_blank">
                                <xsl:attribute name="href">
                                    <xsl:value-of select="episciences/paperLicence/text()"/>
                                </xsl:attribute>
                                <xsl:value-of
                                        select="php:function('Ccsd_Tools::translate', string(episciences/paperLicence))"/>
                            </a>
                        </div>
                    </xsl:when>
                    <xsl:otherwise>
                        <xsl:for-each select="metadata/oai_dc:dc/dc:rights">
                            <xsl:variable name="doc_rights" select="."/>
                            <xsl:if test="not (contains($doc_rights, 'info:eu-repo/semantics/'))">
                                <div class="small">
                                    <xsl:value-of select="php:function('Ccsd_Tools::translate', 'Licence : ')"/>
                                    <a rel="noopener" target="_blank">
                                        <xsl:if test="not (contains($doc_rights, '[CC_NO]'))">
                                            <xsl:attribute name="href">
                                                <xsl:value-of select="$doc_rights"/>
                                            </xsl:attribute>
                                        </xsl:if>
                                        <xsl:value-of select="php:function('Ccsd_Tools::translate', string($doc_rights))"/>
                                    </a>
                                </div>
                            </xsl:if>
                        </xsl:for-each>
                    </xsl:otherwise>
                </xsl:choose>

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