<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.0"
				xmlns:php="http://php.net/xsl"
				xmlns:dc="http://purl.org/dc/elements/1.1/"
				xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/">

	<xsl:output method="text" encoding="utf-8" indent="no" />

	<xsl:template match="/record">

        <xsl:variable name="volume" select="php:function('Ccsd_Tools::protectLatex', concat('volume_',episciences/volume,'_title'))"/>
        <xsl:variable name="review_code" select="php:function('Ccsd_Tools::protectLatex', string(episciences/review_code))" />
        <xsl:variable name="esURL" select="php:function('Ccsd_Tools::protectLatex', string(episciences/esURL))" />
	
		<xsl:text>%% </xsl:text>
		<xsl:value-of select="$review_code" />
		<xsl:text>:</xsl:text>
		<xsl:value-of select="episciences/id" />
		
		<xsl:text>&#xA;%% </xsl:text>
		<xsl:value-of select="$esURL" />
		
		<xsl:text>&#xA;%% </xsl:text>
		
		<xsl:text>&#xA;@article</xsl:text>
		<xsl:text>{</xsl:text>
		<xsl:value-of select="$review_code" />
		<xsl:text>:</xsl:text>
		<xsl:value-of select="episciences/id" /><xsl:text>,</xsl:text>
		<xsl:text>&#10;  TITLE = {{</xsl:text>
		<xsl:value-of select="php:function('Ccsd_Tools::protectLatex', string(metadata/oai_dc:dc/dc:title))" />
		<xsl:text>}},</xsl:text>
		
		<!-- AUTHOR -->
		<xsl:text>&#xA;  AUTHOR = {</xsl:text>
		<xsl:for-each select="metadata/oai_dc:dc/dc:creator">
			<xsl:value-of select="php:function('Episciences_Tools::reformatOaiDcAuthor', string(.), 'true')"/>
			<xsl:if test="position() != last()"> and </xsl:if>
		</xsl:for-each>
		<xsl:text>},</xsl:text>
		
		<!-- URL -->
		<xsl:text>&#xA;  URL = {</xsl:text>
		<xsl:value-of select="$esURL" />
		<xsl:text>},</xsl:text>
		
		<!-- DOI -->
		<xsl:if test="episciences/doi">
		<xsl:text>&#xA;  DOI = {</xsl:text>
		<xsl:value-of select="php:function('Ccsd_Tools::protectLatex', string(episciences/doi))" />
		<xsl:text>},</xsl:text>
		</xsl:if>
		
		<!-- JOURNAL -->
		<xsl:text>&#xA;  JOURNAL = {{</xsl:text>
		<xsl:value-of select="php:function('Ccsd_Tools::protectLatex', string(episciences/review))" />
		<xsl:text>}},</xsl:text>
		
		<!-- VOLUME -->
		<xsl:if test="episciences/volume and episciences/volume != ''">
			<xsl:text>&#xA;  VOLUME = {{</xsl:text> 
    		<xsl:value-of select="php:function('Ccsd_Tools::translate', $volume)" />
    		<xsl:text>}},</xsl:text>
		</xsl:if>
		
		<!-- YEAR -->
		<xsl:if test="episciences/status = 16 and episciences/publication_date">
			<xsl:text>&#xA;  YEAR = {</xsl:text>
    		<xsl:value-of select="php:function('Episciences_View_Helper_Date::Date', string(episciences/publication_date), null, 'y')" />
    		<xsl:text>},</xsl:text>
		</xsl:if>
		
		<!-- MONTH -->
		<xsl:if test="episciences/status = 16 and episciences/publication_date">
			<xsl:text>&#xA;  MONTH = </xsl:text>
    		<xsl:value-of select="php:function('Episciences_View_Helper_Date::Date', string(episciences/publication_date), null, 'MMM')" />
    		<xsl:text>,</xsl:text>
		</xsl:if>
		
		<xsl:text>&#xA;  KEYWORDS = {</xsl:text>
		<xsl:for-each select="metadata/oai_dc:dc/dc:subject">
			<xsl:value-of select="php:function('Ccsd_Tools::protectLatex', string(.))"/>
				<xsl:if test="position() != last()"> ; </xsl:if>
		    </xsl:for-each>
		<xsl:text>},</xsl:text>
		
		<xsl:text>&#xA;}</xsl:text>
	</xsl:template>

</xsl:stylesheet> 